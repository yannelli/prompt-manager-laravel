<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PromptManager\PromptTemplates\Contracts\PipelineStepInterface;
use PromptManager\PromptTemplates\DTOs\PipelineContext;
use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Traits\HasUuid;

class PromptPipelineStep extends Model implements PipelineStepInterface
{
    use HasUuid;

    protected $fillable = [
        'prompt_pipeline_id',
        'prompt_template_id',
        'name',
        'order',
        'handler_class',
        'input_mapping',
        'output_mapping',
        'conditions',
        'continue_on_failure',
        'retry_attempts',
        'config',
        'variable_overrides',
        'is_enabled',
    ];

    protected $casts = [
        'order' => 'integer',
        'input_mapping' => 'array',
        'output_mapping' => 'array',
        'conditions' => 'array',
        'continue_on_failure' => 'boolean',
        'retry_attempts' => 'integer',
        'config' => 'array',
        'variable_overrides' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('prompt-templates.tables.prompt_pipeline_steps', 'prompt_pipeline_steps');
    }

    /**
     * Get the parent pipeline.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(
            config('prompt-templates.models.prompt_pipeline', PromptPipeline::class),
            'prompt_pipeline_id'
        );
    }

    /**
     * Get the template for this step.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(
            config('prompt-templates.models.prompt_template', PromptTemplate::class),
            'prompt_template_id'
        );
    }

    /**
     * Handle/execute this pipeline step.
     */
    public function handle(PipelineContext $context): PipelineContext
    {
        $variables = $this->mapInputVariables($context);

        // Merge with variable overrides
        if ($this->variable_overrides) {
            $variables = array_merge($variables, $this->variable_overrides);
        }

        $result = null;

        // Use custom handler if defined
        if ($this->handler_class) {
            $handler = app($this->handler_class);

            if (method_exists($handler, '__invoke')) {
                $result = $handler($context, $variables, $this->config ?? []);
            } elseif (method_exists($handler, 'handle')) {
                $result = $handler->handle($context, $variables, $this->config ?? []);
            }
        }

        // Otherwise use the template
        if ($result === null && $this->template) {
            $result = $this->template->render($variables, $this->config ?? []);
        }

        if ($result instanceof RenderedPrompt) {
            $context->addResult($this->name, $result);

            // Map output to context
            $this->mapOutputToContext($context, $result);
        }

        return $context;
    }

    /**
     * Check if this step should execute.
     */
    public function shouldExecute(PipelineContext $context): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if (empty($this->conditions)) {
            return true;
        }

        return $this->evaluateConditions($context);
    }

    /**
     * Get the name of this step.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the order of this step.
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Map context data to step input variables.
     */
    protected function mapInputVariables(PipelineContext $context): array
    {
        if (empty($this->input_mapping)) {
            return $context->all();
        }

        $variables = [];

        foreach ($this->input_mapping as $varName => $contextPath) {
            if (is_string($contextPath)) {
                $variables[$varName] = $context->get($contextPath);
            } elseif (is_array($contextPath)) {
                // Complex mapping with transformation
                $value = $context->get($contextPath['from'] ?? $varName);

                if (isset($contextPath['transform']) && is_callable($contextPath['transform'])) {
                    $value = $contextPath['transform']($value);
                }

                if (isset($contextPath['default']) && $value === null) {
                    $value = $contextPath['default'];
                }

                $variables[$varName] = $value;
            }
        }

        return $variables;
    }

    /**
     * Map step output to context.
     */
    protected function mapOutputToContext(PipelineContext $context, RenderedPrompt $result): void
    {
        if (empty($this->output_mapping)) {
            return;
        }

        foreach ($this->output_mapping as $contextPath => $resultProperty) {
            $value = match ($resultProperty) {
                'system_prompt', 'systemPrompt' => $result->systemPrompt,
                'user_prompt', 'userPrompt' => $result->userPrompt,
                'assistant_prompt', 'assistantPrompt' => $result->assistantPrompt,
                'content' => $result->content,
                'messages' => $result->messages,
                'metadata' => $result->metadata,
                'toString', 'string' => $result->toString(),
                default => $result->metadata[$resultProperty] ?? null,
            };

            $context->set($contextPath, $value);
        }
    }

    /**
     * Evaluate step conditions.
     */
    protected function evaluateConditions(PipelineContext $context): bool
    {
        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '==';
            $value = $condition['value'] ?? null;

            if ($field === null) {
                continue;
            }

            $contextValue = $context->get($field);

            $passed = match ($operator) {
                '==' => $contextValue == $value,
                '===' => $contextValue === $value,
                '!=' => $contextValue != $value,
                '!==' => $contextValue !== $value,
                '>' => $contextValue > $value,
                '>=' => $contextValue >= $value,
                '<' => $contextValue < $value,
                '<=' => $contextValue <= $value,
                'in' => is_array($value) && in_array($contextValue, $value),
                'not_in' => is_array($value) && ! in_array($contextValue, $value),
                'contains' => is_string($contextValue) && str_contains($contextValue, $value),
                'not_contains' => is_string($contextValue) && ! str_contains($contextValue, $value),
                'empty' => empty($contextValue),
                'not_empty' => ! empty($contextValue),
                'exists' => $context->has($field),
                'not_exists' => ! $context->has($field),
                default => true,
            };

            if (! $passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get previous step.
     */
    public function getPreviousStep(): ?static
    {
        return static::where('prompt_pipeline_id', $this->prompt_pipeline_id)
            ->where('order', '<', $this->order)
            ->orderByDesc('order')
            ->first();
    }

    /**
     * Get next step.
     */
    public function getNextStep(): ?static
    {
        return static::where('prompt_pipeline_id', $this->prompt_pipeline_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Scope to enabled steps.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
