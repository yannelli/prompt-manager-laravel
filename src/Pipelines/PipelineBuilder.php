<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Pipelines;

use PromptManager\PromptTemplates\DTOs\PipelineContext;
use PromptManager\PromptTemplates\Models\PromptPipeline;
use PromptManager\PromptTemplates\Models\PromptTemplate;

class PipelineBuilder
{
    protected ?PromptPipeline $pipeline = null;

    protected array $steps = [];

    protected array $config = [];

    protected ?string $name = null;

    protected ?string $slug = null;

    protected ?int $userId = null;

    /**
     * Create a new pipeline builder instance.
     */
    public static function create(?string $name = null): static
    {
        $builder = new static;
        $builder->name = $name;

        return $builder;
    }

    /**
     * Start from an existing pipeline.
     */
    public static function from(PromptPipeline|string $pipeline): static
    {
        $builder = new static;

        if (is_string($pipeline)) {
            $pipeline = PromptPipeline::findBySlug($pipeline);
        }

        $builder->pipeline = $pipeline;

        return $builder;
    }

    /**
     * Set the pipeline name.
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the pipeline slug.
     */
    public function slug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Set the owner user ID.
     */
    public function forUser(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Add a template step.
     */
    public function addTemplate(
        PromptTemplate|string $template,
        ?string $stepName = null,
        array $inputMapping = [],
        array $outputMapping = [],
        array $config = []
    ): static {
        if (is_string($template)) {
            $templateModel = config('prompt-templates.models.prompt_template');
            $template = $templateModel::findBySlug($template);
        }

        $this->steps[] = [
            'type' => 'template',
            'template' => $template,
            'name' => $stepName ?? ($template ? $template->slug : 'step_'.count($this->steps)),
            'input_mapping' => $inputMapping,
            'output_mapping' => $outputMapping,
            'config' => $config,
        ];

        return $this;
    }

    /**
     * Add a custom handler step.
     */
    public function addHandler(
        string $handlerClass,
        ?string $stepName = null,
        array $inputMapping = [],
        array $outputMapping = [],
        array $config = []
    ): static {
        $this->steps[] = [
            'type' => 'handler',
            'handler_class' => $handlerClass,
            'name' => $stepName ?? 'handler_'.count($this->steps),
            'input_mapping' => $inputMapping,
            'output_mapping' => $outputMapping,
            'config' => $config,
        ];

        return $this;
    }

    /**
     * Add a conditional step.
     */
    public function addConditional(
        array $conditions,
        PromptTemplate|string $template,
        ?string $stepName = null,
        array $config = []
    ): static {
        if (is_string($template)) {
            $templateModel = config('prompt-templates.models.prompt_template');
            $template = $templateModel::findBySlug($template);
        }

        $this->steps[] = [
            'type' => 'template',
            'template' => $template,
            'name' => $stepName ?? ($template ? $template->slug : 'conditional_'.count($this->steps)),
            'conditions' => $conditions,
            'config' => $config,
        ];

        return $this;
    }

    /**
     * Add a transform step (inline callback).
     */
    public function transform(string $stepName, callable $transformer): static
    {
        $this->steps[] = [
            'type' => 'transform',
            'name' => $stepName,
            'transformer' => $transformer,
        ];

        return $this;
    }

    /**
     * Set pipeline configuration.
     */
    public function config(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Build and save the pipeline.
     */
    public function save(): PromptPipeline
    {
        $pipelineClass = config('prompt-templates.models.prompt_pipeline', PromptPipeline::class);

        $this->pipeline = $pipelineClass::create([
            'name' => $this->name ?? 'Pipeline '.time(),
            'slug' => $this->slug ?? 'pipeline-'.time(),
            'user_id' => $this->userId,
            'config' => $this->config,
            'is_active' => true,
        ]);

        foreach ($this->steps as $order => $step) {
            $this->createStep($step, $order);
        }

        return $this->pipeline;
    }

    /**
     * Execute the pipeline without saving.
     */
    public function execute(array $input = []): PipelineContext
    {
        $executor = app(PipelineExecutor::class);

        if ($this->pipeline) {
            return $executor->execute($this->pipeline, $input);
        }

        // Execute as ad-hoc pipeline
        $context = new PipelineContext(initialInput: $input, config: $this->config);

        foreach ($this->steps as $step) {
            if (! $context->shouldContinue()) {
                break;
            }

            $context = $this->executeStep($step, $context);
        }

        return $context;
    }

    /**
     * Create a step model.
     */
    protected function createStep(array $step, int $order): void
    {
        $stepData = [
            'name' => $step['name'],
            'order' => $order,
            'input_mapping' => $step['input_mapping'] ?? null,
            'output_mapping' => $step['output_mapping'] ?? null,
            'conditions' => $step['conditions'] ?? null,
            'config' => $step['config'] ?? null,
            'is_enabled' => true,
        ];

        if ($step['type'] === 'template' && $step['template']) {
            $stepData['prompt_template_id'] = $step['template']->id;
        }

        if ($step['type'] === 'handler') {
            $stepData['handler_class'] = $step['handler_class'];
        }

        $this->pipeline->steps()->create($stepData);
    }

    /**
     * Execute a single step in ad-hoc mode.
     */
    protected function executeStep(array $step, PipelineContext $context): PipelineContext
    {
        try {
            if ($step['type'] === 'transform' && isset($step['transformer'])) {
                $transformer = $step['transformer'];
                $result = $transformer($context);

                if ($result instanceof PipelineContext) {
                    return $result;
                }

                return $context;
            }

            if ($step['type'] === 'template' && $step['template']) {
                $variables = $this->mapInput($step['input_mapping'] ?? [], $context);
                $result = $step['template']->render($variables, $step['config'] ?? []);
                $context->addResult($step['name'], $result);
                $this->mapOutput($step['output_mapping'] ?? [], $context, $result);
            }

            if ($step['type'] === 'handler') {
                $handler = app($step['handler_class']);
                $variables = $this->mapInput($step['input_mapping'] ?? [], $context);
                $result = $handler($context, $variables, $step['config'] ?? []);

                if ($result instanceof \PromptManager\PromptTemplates\DTOs\RenderedPrompt) {
                    $context->addResult($step['name'], $result);
                }
            }
        } catch (\Throwable $e) {
            $context->addError($step['name'], $e->getMessage());
            $context->stop();
        }

        return $context;
    }

    /**
     * Map input from context.
     */
    protected function mapInput(array $mapping, PipelineContext $context): array
    {
        if (empty($mapping)) {
            return $context->all();
        }

        $variables = [];

        foreach ($mapping as $varName => $contextPath) {
            $variables[$varName] = $context->get($contextPath);
        }

        return $variables;
    }

    /**
     * Map output to context.
     */
    protected function mapOutput(array $mapping, PipelineContext $context, $result): void
    {
        foreach ($mapping as $contextPath => $resultProperty) {
            $value = match ($resultProperty) {
                'toString', 'string' => $result->toString(),
                'content' => $result->content,
                'systemPrompt' => $result->systemPrompt,
                'userPrompt' => $result->userPrompt,
                default => $result->metadata[$resultProperty] ?? null,
            };

            $context->set($contextPath, $value);
        }
    }
}
