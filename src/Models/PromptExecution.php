<?php

namespace Yannelli\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PromptExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'prompt_template_version_id',
        'input_variables',
        'enabled_components',
        'rendered_output',
        'pipeline_chain',
        'execution_time_ms',
        'user_id',
    ];

    protected $casts = [
        'input_variables' => 'array',
        'enabled_components' => 'array',
        'pipeline_chain' => 'array',
        'execution_time_ms' => 'integer',
    ];

    public function getTable(): string
    {
        return config('prompt-manager.tables.executions', 'prompt_executions');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    // Relationships
    public function version(): BelongsTo
    {
        return $this->belongsTo(PromptTemplateVersion::class, 'prompt_template_version_id');
    }

    // Scopes
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeSlowExecutions(Builder $query, int $thresholdMs = 1000): Builder
    {
        return $query->where('execution_time_ms', '>', $thresholdMs);
    }

    // Methods
    public static function track(
        PromptTemplateVersion $version,
        array $inputVariables,
        string $renderedOutput,
        array $options = []
    ): self {
        if (!config('prompt-manager.track_executions', false)) {
            // Return unsaved instance if tracking is disabled
            return new self([
                'prompt_template_version_id' => $version->id,
                'input_variables' => $inputVariables,
                'rendered_output' => $renderedOutput,
                'enabled_components' => $options['enabled_components'] ?? null,
                'pipeline_chain' => $options['pipeline_chain'] ?? null,
                'execution_time_ms' => $options['execution_time_ms'] ?? null,
                'user_id' => $options['user_id'] ?? null,
            ]);
        }

        return static::create([
            'prompt_template_version_id' => $version->id,
            'input_variables' => $inputVariables,
            'rendered_output' => $renderedOutput,
            'enabled_components' => $options['enabled_components'] ?? null,
            'pipeline_chain' => $options['pipeline_chain'] ?? null,
            'execution_time_ms' => $options['execution_time_ms'] ?? null,
            'user_id' => $options['user_id'] ?? null,
        ]);
    }

    public function getTemplate(): ?PromptTemplate
    {
        return $this->version?->template;
    }
}
