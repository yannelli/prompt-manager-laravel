<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PromptManager\PromptTemplates\Contracts\TypeHandlerInterface;

class PromptType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'handler_class',
        'config',
        'schema',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'schema' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('prompt-templates.tables.prompt_types', 'prompt_types');
    }

    /**
     * Get templates of this type.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(
            config('prompt-templates.models.prompt_template', PromptTemplate::class),
            'prompt_type_id'
        );
    }

    /**
     * Get the handler instance for this type.
     */
    public function getHandler(): TypeHandlerInterface
    {
        $handlerClass = $this->handler_class;

        if (! class_exists($handlerClass)) {
            throw new \RuntimeException("Handler class [{$handlerClass}] does not exist.");
        }

        $handler = app($handlerClass);

        if (! $handler instanceof TypeHandlerInterface) {
            throw new \RuntimeException("Handler class [{$handlerClass}] must implement TypeHandlerInterface.");
        }

        if ($this->config) {
            $handler->setConfig($this->config);
        }

        return $handler;
    }

    /**
     * Scope to active types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to system types.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to custom (non-system) types.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Find type by slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }
}
