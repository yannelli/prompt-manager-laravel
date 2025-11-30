<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Pipelines;

use PromptManager\PromptTemplates\DTOs\PipelineContext;
use PromptManager\PromptTemplates\Exceptions\PipelineException;
use PromptManager\PromptTemplates\Models\PromptPipeline;
use PromptManager\PromptTemplates\Models\PromptPipelineStep;

class PipelineExecutor
{
    protected int $maxDepth;

    protected int $currentDepth = 0;

    public function __construct()
    {
        $this->maxDepth = config('prompt-templates.pipeline.max_depth', 10);
    }

    /**
     * Execute a pipeline.
     */
    public function execute(PromptPipeline $pipeline, array $input = [], array $options = []): PipelineContext
    {
        $this->currentDepth++;

        if ($this->currentDepth > $this->maxDepth) {
            throw new PipelineException("Maximum pipeline depth ({$this->maxDepth}) exceeded.");
        }

        try {
            // Validate input
            if (! $pipeline->validateInput($input)) {
                throw new PipelineException('Pipeline input validation failed.');
            }

            $context = new PipelineContext(
                initialInput: $input,
                config: array_merge($pipeline->config ?? [], $options)
            );

            $steps = $pipeline->enabledSteps()->get();

            foreach ($steps as $step) {
                if (! $context->shouldContinue()) {
                    break;
                }

                $context = $this->executeStep($step, $context);
            }

            return $context;
        } finally {
            $this->currentDepth--;
        }
    }

    /**
     * Execute a single pipeline step.
     */
    protected function executeStep(PromptPipelineStep $step, PipelineContext $context): PipelineContext
    {
        if (! $step->shouldExecute($context)) {
            return $context;
        }

        $attempts = 0;
        $maxAttempts = $step->retry_attempts + 1;
        $lastError = null;

        while ($attempts < $maxAttempts) {
            try {
                return $step->handle($context);
            } catch (\Throwable $e) {
                $lastError = $e;
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    break;
                }

                // Exponential backoff
                usleep((int) (100000 * pow(2, $attempts - 1)));
            }
        }

        // Handle failure
        $context->addError($step->getName(), $lastError?->getMessage() ?? 'Unknown error', [
            'attempts' => $attempts,
            'exception' => $lastError ? get_class($lastError) : null,
        ]);

        if (! $step->continue_on_failure) {
            $context->stop();
        }

        return $context;
    }

    /**
     * Execute multiple pipelines in sequence.
     */
    public function chain(array $pipelines, array $input = []): PipelineContext
    {
        $context = new PipelineContext(initialInput: $input);

        foreach ($pipelines as $pipeline) {
            if (! $context->shouldContinue()) {
                break;
            }

            if (is_string($pipeline)) {
                $pipeline = PromptPipeline::findBySlug($pipeline);
            }

            if (! $pipeline instanceof PromptPipeline) {
                throw new PipelineException('Invalid pipeline in chain.');
            }

            $pipelineContext = $this->execute($pipeline, $context->all());

            // Merge results
            foreach ($pipelineContext->getResults() as $name => $result) {
                $context->addResult("{$pipeline->slug}.{$name}", $result);
            }

            // Merge errors
            foreach ($pipelineContext->getErrors() as $name => $error) {
                $context->addError("{$pipeline->slug}.{$name}", $error['message'], $error['context'] ?? []);
            }

            // Merge data
            $context->merge($pipelineContext->all());

            if (! $pipelineContext->shouldContinue()) {
                $context->stop();
            }
        }

        return $context;
    }

    /**
     * Execute multiple pipelines in parallel.
     */
    public function parallel(array $pipelines, array $input = []): array
    {
        $results = [];

        foreach ($pipelines as $name => $pipeline) {
            if (is_string($pipeline)) {
                $pipeline = PromptPipeline::findBySlug($pipeline);
            }

            if (! $pipeline instanceof PromptPipeline) {
                continue;
            }

            $results[$name] = $this->execute($pipeline, $input);
        }

        return $results;
    }

    /**
     * Create an ad-hoc pipeline from templates.
     */
    public function fromTemplates(array $templates, array $input = []): PipelineContext
    {
        $context = new PipelineContext(initialInput: $input);
        $templateModel = config('prompt-templates.models.prompt_template');

        foreach ($templates as $index => $template) {
            if (! $context->shouldContinue()) {
                break;
            }

            if (is_string($template)) {
                $template = $templateModel::findBySlug($template);
            }

            if (! $template) {
                $context->addError("step_{$index}", 'Template not found');

                continue;
            }

            try {
                $result = $template->render($context->all());
                $context->addResult("step_{$index}", $result);
                $context->merge(['last_result' => $result->toString()]);
            } catch (\Throwable $e) {
                $context->addError("step_{$index}", $e->getMessage());
                $context->stop();
            }
        }

        return $context;
    }

    /**
     * Get current execution depth.
     */
    public function getCurrentDepth(): int
    {
        return $this->currentDepth;
    }

    /**
     * Get maximum allowed depth.
     */
    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * Set maximum allowed depth.
     */
    public function setMaxDepth(int $maxDepth): static
    {
        $this->maxDepth = $maxDepth;

        return $this;
    }
}
