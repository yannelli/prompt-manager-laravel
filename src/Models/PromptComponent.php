<?php

namespace Yannelli\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PromptComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'prompt_template_id',
        'key',
        'name',
        'content',
        'position',
        'order',
        'is_default_enabled',
        'conditions',
    ];

    protected $casts = [
        'is_default_enabled' => 'boolean',
        'conditions' => 'array',
        'order' => 'integer',
    ];

    public function getTable(): string
    {
        return config('prompt-manager.tables.components', 'prompt_components');
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

    // Scopes
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_default_enabled', true);
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('is_default_enabled', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    public function scopeAtPosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position);
    }

    // Methods
    public function shouldBeEnabled(array $context = []): bool
    {
        if (empty($this->conditions)) {
            return $this->is_default_enabled;
        }

        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return true;
        }

        $contextValue = data_get($context, $field);

        if ($contextValue === null && !isset($context[$field])) {
            // Field not present in context, condition doesn't apply
            return $condition['allow_missing'] ?? true;
        }

        return match ($operator) {
            '=', '==' => $contextValue == $value,
            '===' => $contextValue === $value,
            '!=' => $contextValue != $value,
            '!==' => $contextValue !== $value,
            '>' => $contextValue > $value,
            '>=' => $contextValue >= $value,
            '<' => $contextValue < $value,
            '<=' => $contextValue <= $value,
            'in' => in_array($contextValue, (array) $value, true),
            'not_in' => !in_array($contextValue, (array) $value, true),
            'contains' => is_string($contextValue) && str_contains($contextValue, $value),
            'not_contains' => is_string($contextValue) && !str_contains($contextValue, $value),
            'starts_with' => is_string($contextValue) && str_starts_with($contextValue, $value),
            'ends_with' => is_string($contextValue) && str_ends_with($contextValue, $value),
            'regex' => is_string($contextValue) && preg_match($value, $contextValue),
            'empty' => empty($contextValue),
            'not_empty' => !empty($contextValue),
            'is_null' => $contextValue === null,
            'not_null' => $contextValue !== null,
            default => true,
        };
    }

    public function enable(): self
    {
        $this->update(['is_default_enabled' => true]);
        return $this;
    }

    public function disable(): self
    {
        $this->update(['is_default_enabled' => false]);
        return $this;
    }

    public function isPrepend(): bool
    {
        return $this->position === 'prepend';
    }

    public function isAppend(): bool
    {
        return $this->position === 'append';
    }

    public function isReplace(): bool
    {
        return str_starts_with($this->position, 'replace:');
    }

    public function getReplaceMarker(): ?string
    {
        if (!$this->isReplace()) {
            return null;
        }

        return substr($this->position, 8);
    }
}
