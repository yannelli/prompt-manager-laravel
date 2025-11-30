<?php

namespace Yannelli\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Yannelli\PromptManager\Contracts\PromptTypeInterface;
use Yannelli\PromptManager\Exceptions\InvalidTypeException;

class PromptTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'description',
        'type',
        'metadata',
        'is_active',
        'current_version_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('prompt-manager.tables.templates', 'prompt_templates');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    // Relationships
    public function versions(): HasMany
    {
        return $this->hasMany(PromptTemplateVersion::class)
                    ->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PromptTemplateVersion::class, 'current_version_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(PromptComponent::class)->orderBy('order');
    }

    public function latestVersion(): HasMany
    {
        return $this->hasMany(PromptTemplateVersion::class)
                    ->orderByDesc('version_number')
                    ->limit(1);
    }

    // Accessors
    public function getTypeHandlerAttribute(): PromptTypeInterface
    {
        $typeClass = config("prompt-manager.types.{$this->type}");

        if (!$typeClass) {
            $typeClass = config('prompt-manager.default_type');
        }

        if (!$typeClass) {
            throw InvalidTypeException::notFound($this->type);
        }

        $handler = app()->make($typeClass);

        if (!$handler instanceof PromptTypeInterface) {
            throw InvalidTypeException::invalidClass($this->type, $typeClass);
        }

        return $handler;
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    // Methods
    public function createVersion(string $content, array $options = []): PromptTemplateVersion
    {
        $latestVersionNumber = $this->versions()->max('version_number') ?? 0;

        $version = $this->versions()->create([
            'version_number' => $latestVersionNumber + 1,
            'content' => $content,
            'variables' => $options['variables'] ?? null,
            'component_config' => $options['component_config'] ?? null,
            'mapping_rules' => $options['mapping_rules'] ?? null,
            'change_summary' => $options['change_summary'] ?? null,
            'created_by' => $options['created_by'] ?? null,
            'is_published' => $options['is_published'] ?? config('prompt-manager.versioning.auto_publish', false),
        ]);

        if ($options['set_as_current'] ?? true) {
            $this->update(['current_version_id' => $version->id]);
        }

        return $version;
    }

    public function getVersion(?int $versionNumber = null): ?PromptTemplateVersion
    {
        if ($versionNumber === null) {
            return $this->currentVersion ?? $this->versions()->first();
        }

        return $this->versions()->where('version_number', $versionNumber)->first();
    }

    public function activate(): self
    {
        $this->update(['is_active' => true]);
        return $this;
    }

    public function deactivate(): self
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    public function addComponent(array $data): PromptComponent
    {
        return $this->components()->create($data);
    }

    public function getEffectiveVersion(): ?PromptTemplateVersion
    {
        return $this->currentVersion ?? $this->versions()->first();
    }
}
