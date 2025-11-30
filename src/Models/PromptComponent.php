<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PromptManager\PromptTemplates\Traits\HasUuid;

class PromptComponent extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'content',
        'position',
        'type',
        'variables',
        'metadata',
        'user_id',
        'is_global',
        'is_system',
        'is_active',
        'default_order',
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata' => 'array',
        'is_global' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'default_order' => 'integer',
    ];

    public function getTable(): string
    {
        return config('prompt-templates.tables.prompt_components', 'prompt_components');
    }

    /**
     * Get the owner of this component.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('prompt-templates.user_model', 'App\\Models\\User'), 'user_id');
    }

    /**
     * Get templates using this component.
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(
            config('prompt-templates.models.prompt_template', PromptTemplate::class),
            config('prompt-templates.tables.prompt_template_components', 'prompt_template_components'),
            'prompt_component_id',
            'prompt_template_id'
        )->withPivot([
            'user_id',
            'is_enabled',
            'order',
            'target',
            'position',
            'config',
            'variable_overrides',
            'conditions',
        ])->withTimestamps();
    }

    /**
     * Render this component with variables.
     */
    public function render(array $variables = []): string
    {
        $content = $this->content;
        $delimiters = config('prompt-templates.variable_delimiters', [
            'start' => '{{',
            'end' => '}}',
        ]);

        $pattern = '/'.preg_quote($delimiters['start'], '/')
            .'\\s*([\\w.]+)\\s*'
            .preg_quote($delimiters['end'], '/').'/';

        return preg_replace_callback($pattern, function ($matches) use ($variables) {
            $key = $matches[1];

            return $variables[$key] ?? $matches[0];
        }, $content);
    }

    /**
     * Get expected variables from content.
     */
    public function getExpectedVariables(): array
    {
        $defined = $this->variables ?? [];
        $delimiters = config('prompt-templates.variable_delimiters', [
            'start' => '{{',
            'end' => '}}',
        ]);

        $pattern = '/'.preg_quote($delimiters['start'], '/')
            .'\\s*([\\w.]+)\\s*'
            .preg_quote($delimiters['end'], '/').'/';

        preg_match_all($pattern, $this->content, $matches);

        $detected = array_unique($matches[1] ?? []);

        // Merge detected variables with defined ones
        foreach ($detected as $var) {
            if (! isset($defined[$var])) {
                $defined[$var] = [
                    'type' => 'string',
                    'required' => false,
                ];
            }
        }

        return $defined;
    }

    /**
     * Duplicate this component.
     */
    public function duplicate(?string $newName = null, ?string $newSlug = null): static
    {
        $new = $this->replicate(['uuid']);
        $new->name = $newName ?? $this->name.' (Copy)';
        $new->slug = $newSlug ?? $this->slug.'-copy-'.time();
        $new->is_system = false;
        $new->save();

        return $new;
    }

    /**
     * Scope to active components.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to global components.
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    /**
     * Scope to system components.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to components of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to components available to a user.
     */
    public function scopeAvailableTo($query, ?int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_global', true);

            if ($userId !== null) {
                $q->orWhere('user_id', $userId);
            }
        });
    }

    /**
     * Find by slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }
}
