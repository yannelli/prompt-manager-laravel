<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Support;

use PromptManager\PromptTemplates\Contracts\VersionMapperInterface;
use PromptManager\PromptTemplates\DTOs\VersionMappingResult;
use PromptManager\PromptTemplates\Models\PromptTemplateVersion;

abstract class AbstractVersionMapper implements VersionMapperInterface
{
    /**
     * The source version this mapper handles.
     */
    protected int $sourceVersion = 1;

    /**
     * The target version this mapper produces.
     */
    protected int $targetVersion = 2;

    /**
     * Map content from one version to another.
     */
    abstract public function map(PromptTemplateVersion $fromVersion, PromptTemplateVersion $toVersion, array $content): VersionMappingResult;

    /**
     * Map variables from one version to another.
     */
    public function mapVariables(PromptTemplateVersion $fromVersion, PromptTemplateVersion $toVersion, array $variables): array
    {
        return $variables;
    }

    /**
     * Check if this mapper can handle the version transition.
     */
    public function supports(PromptTemplateVersion $fromVersion, PromptTemplateVersion $toVersion): bool
    {
        return $fromVersion->version === $this->sourceVersion
            && $toVersion->version === $this->targetVersion;
    }

    /**
     * Get the source version this mapper handles.
     */
    public function getSourceVersion(): int
    {
        return $this->sourceVersion;
    }

    /**
     * Get the target version this mapper produces.
     */
    public function getTargetVersion(): int
    {
        return $this->targetVersion;
    }

    /**
     * Helper to rename a key in content.
     */
    protected function renameKey(array $content, string $from, string $to): array
    {
        if (isset($content[$from])) {
            $content[$to] = $content[$from];
            unset($content[$from]);
        }

        return $content;
    }

    /**
     * Helper to remove a key from content.
     */
    protected function removeKey(array $content, string $key): array
    {
        unset($content[$key]);

        return $content;
    }

    /**
     * Helper to set a default value if key doesn't exist.
     */
    protected function setDefault(array $content, string $key, mixed $default): array
    {
        if (! isset($content[$key])) {
            $content[$key] = $default;
        }

        return $content;
    }

    /**
     * Helper to transform a value.
     */
    protected function transformValue(array $content, string $key, callable $transformer): array
    {
        if (isset($content[$key])) {
            $content[$key] = $transformer($content[$key]);
        }

        return $content;
    }
}
