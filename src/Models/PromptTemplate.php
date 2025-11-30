<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PromptManager\PromptTemplates\Contracts\RenderableInterface;
use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Traits\HasUuid;

class PromptTemplate extends Model implements RenderableInterface
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'system_prompt',
        'user_prompt',
        'assistant_prompt',
        'content',
        'prompt_type_id',
        'current_version',
        'user_id',
        'created_by',
        'updated_by',
        'variables',
        'metadata',
        'settings',
        'is_active',
        'is_locked',
        'published_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
        'published_at' => 'datetime',
        'current_version' => 'integer',
    ];

    public function getTable(): string
    {
        return config('prompt-templates.tables.prompt_templates', 'prompt_templates');
    }

    /**
     * Get the prompt type.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(
            config('prompt-templates.models.prompt_type', PromptType::class),
            'prompt_type_id'
        );
    }

    /**
     * Get the versions for this template.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(
            config('prompt-templates.models.prompt_template_version', PromptTemplateVersion::class),
            'prompt_template_id'
        );
    }

    /**
     * Get the components attached to this template.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            config('prompt-templates.models.prompt_component', PromptComponent::class),
            config('prompt-templates.tables.prompt_template_components', 'prompt_template_components'),
            'prompt_template_id',
            'prompt_component_id'
        )->withPivot([
            'user_id',
            'is_enabled',
            'order',
            'target',
            'position',
            'config',
            'variable_overrides',
            'conditions',
        ])->withTimestamps()->orderByPivot('order');
    }

    /**
     * Get enabled components for a specific user.
     */
    public function enabledComponentsForUser(?int $userId = null): BelongsToMany
    {
        $query = $this->components()->wherePivot('is_enabled', true);

        if ($userId !== null) {
            $query->wherePivot('user_id', $userId);
        } else {
            $query->wherePivotNull('user_id');
        }

        return $query;
    }

    /**
     * Get the owner of this template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('prompt-templates.user_model', 'App\\Models\\User'), 'user_id');
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('prompt-templates.user_model', 'App\\Models\\User'), 'created_by');
    }

    /**
     * Get the last updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(config('prompt-templates.user_model', 'App\\Models\\User'), 'updated_by');
    }

    /**
     * Render the template with variables.
     */
    public function render(array $variables = [], array $options = []): RenderedPrompt
    {
        // Use the type handler if available
        if ($this->type) {
            return $this->type->getHandler()($this, $variables, $options);
        }

        // Default rendering
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
     * Create a new version from current state.
     */
    public function createVersion(?string $changelog = null, ?int $createdBy = null): PromptTemplateVersion
    {
        $versionClass = config('prompt-templates.models.prompt_template_version', PromptTemplateVersion::class);

        $version = new $versionClass([
            'prompt_template_id' => $this->id,
            'version' => $this->current_version,
            'system_prompt' => $this->system_prompt,
            'user_prompt' => $this->user_prompt,
            'assistant_prompt' => $this->assistant_prompt,
            'content' => $this->content,
            'variables' => $this->variables,
            'metadata' => $this->metadata,
            'changelog' => $changelog,
            'created_by' => $createdBy,
        ]);

        $version->save();

        $this->increment('current_version');

        return $version;
    }

    /**
     * Get a specific version.
     */
    public function getVersion(int $version): ?PromptTemplateVersion
    {
        return $this->versions()->where('version', $version)->first();
    }

    /**
     * Get the latest stable version.
     */
    public function getStableVersion(): ?PromptTemplateVersion
    {
        return $this->versions()->where('is_stable', true)->latest('version')->first();
    }

    /**
     * Restore from a specific version.
     */
    public function restoreFromVersion(int $version): bool
    {
        $versionModel = $this->getVersion($version);

        if (! $versionModel) {
            return false;
        }

        $this->system_prompt = $versionModel->system_prompt;
        $this->user_prompt = $versionModel->user_prompt;
        $this->assistant_prompt = $versionModel->assistant_prompt;
        $this->content = $versionModel->content;
        $this->variables = $versionModel->variables;

        return $this->save();
    }

    /**
     * Duplicate this template.
     */
    public function duplicate(?string $newName = null, ?string $newSlug = null): static
    {
        $new = $this->replicate(['uuid']);
        $new->name = $newName ?? $this->name.' (Copy)';
        $new->slug = $newSlug ?? $this->slug.'-copy-'.time();
        $new->current_version = 1;
        $new->is_locked = false;
        $new->published_at = null;
        $new->save();

        // Copy components
        foreach ($this->components as $component) {
            $new->components()->attach($component->id, [
                'is_enabled' => $component->pivot->is_enabled,
                'order' => $component->pivot->order,
                'target' => $component->pivot->target,
                'position' => $component->pivot->position,
                'config' => $component->pivot->config,
                'variable_overrides' => $component->pivot->variable_overrides,
                'conditions' => $component->pivot->conditions,
            ]);
        }

        return $new;
    }

    /**
     * Scope to active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to published templates.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to templates owned by user.
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Find by slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }
}
