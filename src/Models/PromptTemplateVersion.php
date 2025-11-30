<?php

namespace Yannelli\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PromptTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'prompt_template_id',
        'version_number',
        'content',
        'variables',
        'component_config',
        'mapping_rules',
        'change_summary',
        'created_by',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'component_config' => 'array',
        'mapping_rules' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('prompt-manager.tables.versions', 'prompt_template_versions');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PromptExecution::class, 'prompt_template_version_id');
    }

    // Scopes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeUnpublished(Builder $query): Builder
    {
        return $query->where('is_published', false);
    }

    // Methods
    public function publish(): self
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return $this;
    }

    public function unpublish(): self
    {
        $this->update([
            'is_published' => false,
            'published_at' => null,
        ]);

        return $this;
    }

    public function setAsCurrent(): self
    {
        $this->template->update(['current_version_id' => $this->id]);
        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->template->current_version_id === $this->id;
    }

    public function getPreviousVersion(): ?self
    {
        return static::where('prompt_template_id', $this->prompt_template_id)
                     ->where('version_number', '<', $this->version_number)
                     ->orderByDesc('version_number')
                     ->first();
    }

    public function getNextVersion(): ?self
    {
        return static::where('prompt_template_id', $this->prompt_template_id)
                     ->where('version_number', '>', $this->version_number)
                     ->orderBy('version_number')
                     ->first();
    }

    public function diff(?self $compareWith = null): array
    {
        $compareWith = $compareWith ?? $this->getPreviousVersion();

        if (!$compareWith) {
            return [
                'old_content' => '',
                'new_content' => $this->content,
                'has_changes' => true,
                'variables_changed' => true,
            ];
        }

        return [
            'old_content' => $compareWith->content,
            'new_content' => $this->content,
            'has_changes' => $compareWith->content !== $this->content,
            'variables_changed' => $this->variables !== $compareWith->variables,
            'old_variables' => $compareWith->variables,
            'new_variables' => $this->variables,
        ];
    }

    public function getExpectedVariables(): array
    {
        if (!empty($this->variables)) {
            return $this->variables;
        }

        // Parse template content for variables like {{ variable_name }}
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $this->content, $matches);

        return array_unique($matches[1] ?? []);
    }

    public function duplicate(): self
    {
        return $this->template->createVersion($this->content, [
            'variables' => $this->variables,
            'component_config' => $this->component_config,
            'mapping_rules' => $this->mapping_rules,
            'change_summary' => 'Duplicated from version ' . $this->version_number,
            'set_as_current' => false,
        ]);
    }
}
