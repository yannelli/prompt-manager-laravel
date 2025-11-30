<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Services;

use PromptManager\PromptTemplates\Contracts\VersionMapperInterface;
use PromptManager\PromptTemplates\DTOs\VersionMappingResult;
use PromptManager\PromptTemplates\Models\PromptTemplate;
use PromptManager\PromptTemplates\Models\PromptTemplateVersion;

class VersionManager
{
    /**
     * Registered version mappers.
     */
    protected array $mappers = [];

    /**
     * Register a version mapper.
     */
    public function registerMapper(string $name, VersionMapperInterface $mapper): static
    {
        $this->mappers[$name] = $mapper;

        return $this;
    }

    /**
     * Get a registered mapper.
     */
    public function getMapper(string $name): ?VersionMapperInterface
    {
        return $this->mappers[$name] ?? null;
    }

    /**
     * Get all registered mappers.
     */
    public function getMappers(): array
    {
        return $this->mappers;
    }

    /**
     * Create a new version for a template.
     */
    public function createVersion(
        PromptTemplate $template,
        ?string $changelog = null,
        ?int $createdBy = null,
        ?string $mapperClass = null,
        ?array $mappingRules = null
    ): PromptTemplateVersion {
        $versionClass = config('prompt-templates.models.prompt_template_version', PromptTemplateVersion::class);

        $version = new $versionClass([
            'prompt_template_id' => $template->id,
            'version' => $template->current_version,
            'system_prompt' => $template->system_prompt,
            'user_prompt' => $template->user_prompt,
            'assistant_prompt' => $template->assistant_prompt,
            'content' => $template->content,
            'variables' => $template->variables,
            'metadata' => $template->metadata,
            'changelog' => $changelog,
            'created_by' => $createdBy,
            'mapper_class' => $mapperClass,
            'mapping_rules' => $mappingRules,
        ]);

        $version->save();

        $template->increment('current_version');

        return $version;
    }

    /**
     * Map content between versions.
     */
    public function mapContent(
        PromptTemplateVersion $fromVersion,
        PromptTemplateVersion $toVersion,
        array $content
    ): VersionMappingResult {
        // First try the version's own mapper
        $mapper = $fromVersion->getMapper();

        if ($mapper && $mapper->supports($fromVersion, $toVersion)) {
            return $mapper->map($fromVersion, $toVersion, $content);
        }

        // Try to find a registered mapper
        foreach ($this->mappers as $registeredMapper) {
            if ($registeredMapper->supports($fromVersion, $toVersion)) {
                return $registeredMapper->map($fromVersion, $toVersion, $content);
            }
        }

        // Try to build a migration path
        return $this->buildMigrationPath($fromVersion, $toVersion, $content);
    }

    /**
     * Build a migration path through intermediate versions.
     */
    protected function buildMigrationPath(
        PromptTemplateVersion $fromVersion,
        PromptTemplateVersion $toVersion,
        array $content
    ): VersionMappingResult {
        $template = $fromVersion->template;
        $currentVersion = $fromVersion->version;
        $targetVersion = $toVersion->version;

        if ($currentVersion === $targetVersion) {
            return VersionMappingResult::success(
                content: $content,
                fromVersion: $currentVersion,
                toVersion: $targetVersion
            );
        }

        $direction = $currentVersion < $targetVersion ? 1 : -1;
        $transformations = [];
        $warnings = [];

        while ($currentVersion !== $targetVersion) {
            $nextVersion = $currentVersion + $direction;
            $current = $template->getVersion($currentVersion);
            $next = $template->getVersion($nextVersion);

            if (! $current || ! $next) {
                return VersionMappingResult::failure(
                    error: "Cannot find version {$nextVersion} in migration path",
                    fromVersion: $fromVersion->version,
                    toVersion: $toVersion->version
                );
            }

            // Try to map this step
            $stepResult = $current->mapTo($next, $content);

            if (! $stepResult->success) {
                return $stepResult;
            }

            $content = $stepResult->content;
            $transformations = array_merge($transformations, $stepResult->transformations);
            $warnings = array_merge($warnings, $stepResult->warnings);

            $currentVersion = $nextVersion;
        }

        return VersionMappingResult::success(
            content: $content,
            fromVersion: $fromVersion->version,
            toVersion: $toVersion->version,
            transformations: $transformations,
            warnings: $warnings
        );
    }

    /**
     * Get diff between two versions.
     */
    public function diff(PromptTemplateVersion $versionA, PromptTemplateVersion $versionB): array
    {
        $diff = [];
        $fields = ['system_prompt', 'user_prompt', 'assistant_prompt', 'content', 'variables', 'metadata'];

        foreach ($fields as $field) {
            $valueA = $versionA->{$field};
            $valueB = $versionB->{$field};

            if ($valueA !== $valueB) {
                $diff[$field] = [
                    'from' => $valueA,
                    'to' => $valueB,
                ];
            }
        }

        return $diff;
    }

    /**
     * Get version history for a template.
     */
    public function getHistory(PromptTemplate $template, int $limit = 10): \Illuminate\Support\Collection
    {
        return $template->versions()
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    /**
     * Restore template to a specific version.
     */
    public function restoreToVersion(PromptTemplate $template, int $version): bool
    {
        $versionModel = $template->getVersion($version);

        if (! $versionModel) {
            return false;
        }

        // Create a snapshot of current state before restoring
        $this->createVersion($template, "Snapshot before restore to v{$version}");

        // Restore the content
        $template->system_prompt = $versionModel->system_prompt;
        $template->user_prompt = $versionModel->user_prompt;
        $template->assistant_prompt = $versionModel->assistant_prompt;
        $template->content = $versionModel->content;
        $template->variables = $versionModel->variables;
        $template->metadata = $versionModel->metadata;

        return $template->save();
    }
}
