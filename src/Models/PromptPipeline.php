<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PromptManager\PromptTemplates\DTOs\PipelineContext;
use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Pipelines\PipelineExecutor;
use PromptManager\PromptTemplates\Traits\HasUuid;

class PromptPipeline extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'user_id',
        'config',
        'input_schema',
        'output_schema',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'input_schema' => 'array',
        'output_schema' => 'array',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('prompt-templates.tables.prompt_pipelines', 'prompt_pipelines');
    }

    /**
     * Get the owner of this pipeline.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('prompt-templates.user_model', 'App\\Models\\User'), 'user_id');
    }

    /**
     * Get the steps for this pipeline.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(
            config('prompt-templates.models.prompt_pipeline_step', PromptPipelineStep::class),
            'prompt_pipeline_id'
        )->orderBy('order');
    }

    /**
     * Get enabled steps.
     */
    public function enabledSteps(): HasMany
    {
        return $this->steps()->where('is_enabled', true);
    }

    /**
     * Execute this pipeline.
     */
    public function execute(array $input = [], array $options = []): PipelineContext
    {
        $executor = app(PipelineExecutor::class);

        return $executor->execute($this, $input, $options);
    }

    /**
     * Execute and get the final rendered prompt.
     */
    public function render(array $input = [], array $options = []): ?RenderedPrompt
    {
        $context = $this->execute($input, $options);

        return $context->getLastResult();
    }

    /**
     * Add a step to the pipeline.
     */
    public function addStep(
        string $name,
        ?PromptTemplate $template = null,
        ?string $handlerClass = null,
        array $config = []
    ): PromptPipelineStep {
        $maxOrder = $this->steps()->max('order') ?? -1;

        $stepClass = config('prompt-templates.models.prompt_pipeline_step', PromptPipelineStep::class);

        return $this->steps()->create([
            'name' => $name,
            'prompt_template_id' => $template?->id,
            'handler_class' => $handlerClass,
            'order' => $maxOrder + 1,
            'config' => $config,
        ]);
    }

    /**
     * Remove a step by name.
     */
    public function removeStep(string $name): bool
    {
        return $this->steps()->where('name', $name)->delete() > 0;
    }

    /**
     * Reorder steps.
     */
    public function reorderSteps(array $stepIds): void
    {
        foreach ($stepIds as $order => $stepId) {
            $this->steps()->where('id', $stepId)->update(['order' => $order]);
        }
    }

    /**
     * Duplicate this pipeline.
     */
    public function duplicate(?string $newName = null, ?string $newSlug = null): static
    {
        $new = $this->replicate(['uuid']);
        $new->name = $newName ?? $this->name.' (Copy)';
        $new->slug = $newSlug ?? $this->slug.'-copy-'.time();
        $new->save();

        foreach ($this->steps as $step) {
            $new->steps()->create([
                'name' => $step->name,
                'prompt_template_id' => $step->prompt_template_id,
                'handler_class' => $step->handler_class,
                'order' => $step->order,
                'input_mapping' => $step->input_mapping,
                'output_mapping' => $step->output_mapping,
                'conditions' => $step->conditions,
                'continue_on_failure' => $step->continue_on_failure,
                'retry_attempts' => $step->retry_attempts,
                'config' => $step->config,
                'variable_overrides' => $step->variable_overrides,
                'is_enabled' => $step->is_enabled,
            ]);
        }

        return $new;
    }

    /**
     * Validate input against schema.
     */
    public function validateInput(array $input): bool
    {
        if (empty($this->input_schema)) {
            return true;
        }

        // Basic validation against schema
        foreach ($this->input_schema as $field => $rules) {
            $required = $rules['required'] ?? false;

            if ($required && ! array_key_exists($field, $input)) {
                return false;
            }

            if (isset($input[$field]) && isset($rules['type'])) {
                $type = $rules['type'];
                $value = $input[$field];

                $valid = match ($type) {
                    'string' => is_string($value),
                    'integer', 'int' => is_int($value),
                    'float', 'number' => is_numeric($value),
                    'boolean', 'bool' => is_bool($value),
                    'array' => is_array($value),
                    default => true,
                };

                if (! $valid) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Scope to active pipelines.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to pipelines owned by user.
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
