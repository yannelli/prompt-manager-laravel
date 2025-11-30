<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PromptManager\PromptTemplates\Contracts\RenderableInterface;
use PromptManager\PromptTemplates\Contracts\VersionMapperInterface;
use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\DTOs\VersionMappingResult;
use PromptManager\PromptTemplates\Traits\HasUuid;

class PromptTemplateVersion extends Model implements RenderableInterface
{
    use HasUuid;

    protected $fillable = [
        'prompt_template_id',
        'version',
        'system_prompt',
        'user_prompt',
        'assistant_prompt',
        'content',
        'mapper_class',
        'mapping_rules',
        'changelog',
        'variables',
        'metadata',
        'created_by',
        'is_stable',
        'is_deprecated',
        'deprecated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'mapping_rules' => 'array',
        'variables' => 'array',
        'metadata' => 'array',
        'is_stable' => 'boolean',
        'is_deprecated' => 'boolean',
        'deprecated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('prompt-templates.tables.prompt_template_versions', 'prompt_template_versions');
    }

    /**
     * Get the parent template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(
            config('prompt-templates.models.prompt_template', PromptTemplate::class),
            'prompt_template_id'
        );
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('prompt-templates.user_model', 'App\\Models\\User'), 'created_by');
    }

    /**
     * Render this version.
     */
    public function render(array $variables = [], array $options = []): RenderedPrompt
    {
        return app('prompt-templates.renderer')->render($this, $variables, $options);
    }

    /**
     * Get expected variables.
     */
    public function getExpectedVariables(): array
    {
        return $this->variables ?? [];
    }

    /**
     * Validate provided variables.
     */
    public function validateVariables(array $variables): bool
    {
        $expected = $this->getExpectedVariables();

        foreach ($expected as $name => $config) {
            $required = $config['required'] ?? false;
            $hasDefault = array_key_exists('default', $config);

            if ($required && ! array_key_exists($name, $variables) && ! $hasDefault) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the version mapper instance.
     */
    public function getMapper(): ?VersionMapperInterface
    {
        if (! $this->mapper_class) {
            return null;
        }

        if (! class_exists($this->mapper_class)) {
            throw new \RuntimeException("Mapper class [{$this->mapper_class}] does not exist.");
        }

        $mapper = app($this->mapper_class);

        if (! $mapper instanceof VersionMapperInterface) {
            throw new \RuntimeException("Mapper class [{$this->mapper_class}] must implement VersionMapperInterface.");
        }

        return $mapper;
    }

    /**
     * Map this version to another version.
     */
    public function mapTo(PromptTemplateVersion $targetVersion, array $content): VersionMappingResult
    {
        $mapper = $this->getMapper();

        if ($mapper && $mapper->supports($this, $targetVersion)) {
            return $mapper->map($this, $targetVersion, $content);
        }

        // Use mapping rules if available
        if ($this->mapping_rules) {
            return $this->applyMappingRules($targetVersion, $content);
        }

        // Return content as-is if no mapping defined
        return VersionMappingResult::success(
            content: $content,
            fromVersion: $this->version,
            toVersion: $targetVersion->version,
            warnings: ['No mapper defined, content passed through unchanged.']
        );
    }

    /**
     * Apply mapping rules to content.
     */
    protected function applyMappingRules(PromptTemplateVersion $targetVersion, array $content): VersionMappingResult
    {
        $rules = $this->mapping_rules;
        $transformations = [];

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? 'rename';
            $from = $rule['from'] ?? null;
            $to = $rule['to'] ?? null;

            switch ($type) {
                case 'rename':
                    if ($from && $to && isset($content[$from])) {
                        $content[$to] = $content[$from];
                        unset($content[$from]);
                        $transformations[] = "Renamed '{$from}' to '{$to}'";
                    }
                    break;

                case 'remove':
                    if ($from && isset($content[$from])) {
                        unset($content[$from]);
                        $transformations[] = "Removed '{$from}'";
                    }
                    break;

                case 'transform':
                    if ($from && isset($content[$from]) && isset($rule['callback'])) {
                        $callback = $rule['callback'];
                        if (is_callable($callback)) {
                            $content[$to ?? $from] = $callback($content[$from]);
                            if ($to && $to !== $from) {
                                unset($content[$from]);
                            }
                            $transformations[] = "Transformed '{$from}'";
                        }
                    }
                    break;

                case 'default':
                    if ($to && ! isset($content[$to])) {
                        $content[$to] = $rule['value'] ?? null;
                        $transformations[] = "Added default for '{$to}'";
                    }
                    break;
            }
        }

        return VersionMappingResult::success(
            content: $content,
            fromVersion: $this->version,
            toVersion: $targetVersion->version,
            transformations: $transformations
        );
    }

    /**
     * Mark this version as stable.
     */
    public function markAsStable(): bool
    {
        // Unmark other versions as stable
        $this->template->versions()
            ->where('id', '!=', $this->id)
            ->update(['is_stable' => false]);

        $this->is_stable = true;

        return $this->save();
    }

    /**
     * Mark this version as deprecated.
     */
    public function deprecate(): bool
    {
        $this->is_deprecated = true;
        $this->deprecated_at = now();

        return $this->save();
    }

    /**
     * Get the previous version.
     */
    public function getPreviousVersion(): ?static
    {
        return static::where('prompt_template_id', $this->prompt_template_id)
            ->where('version', '<', $this->version)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Get the next version.
     */
    public function getNextVersion(): ?static
    {
        return static::where('prompt_template_id', $this->prompt_template_id)
            ->where('version', '>', $this->version)
            ->orderBy('version')
            ->first();
    }

    /**
     * Scope to stable versions.
     */
    public function scopeStable($query)
    {
        return $query->where('is_stable', true);
    }

    /**
     * Scope to non-deprecated versions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deprecated', false);
    }
}
