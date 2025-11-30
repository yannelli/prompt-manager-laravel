<?php

declare(strict_types=1);

use PromptManager\PromptTemplates\DTOs\PipelineContext;
use PromptManager\PromptTemplates\Models\PromptPipeline;
use PromptManager\PromptTemplates\Models\PromptTemplate;
use PromptManager\PromptTemplates\Models\PromptType;
use PromptManager\PromptTemplates\Pipelines\PipelineBuilder;
use PromptManager\PromptTemplates\Pipelines\PipelineExecutor;

beforeEach(function () {
    $this->type = PromptType::create([
        'name' => 'Default',
        'slug' => 'default',
        'handler_class' => \PromptManager\PromptTemplates\Handlers\DefaultTypeHandler::class,
        'is_system' => true,
        'is_active' => true,
    ]);
});

it('can create a pipeline', function () {
    $pipeline = PromptPipeline::create([
        'name' => 'Test Pipeline',
        'slug' => 'test-pipeline',
        'is_active' => true,
    ]);

    expect($pipeline)->toBeInstanceOf(PromptPipeline::class)
        ->and($pipeline->uuid)->not->toBeNull();
});

it('can add steps to a pipeline', function () {
    $pipeline = PromptPipeline::create([
        'name' => 'Steps Pipeline',
        'slug' => 'steps-pipeline',
    ]);

    $template = PromptTemplate::create([
        'name' => 'Step Template',
        'slug' => 'step-template',
        'user_prompt' => 'Process: {{ input }}',
        'prompt_type_id' => $this->type->id,
    ]);

    $step = $pipeline->addStep('process', $template);

    expect($pipeline->steps()->count())->toBe(1)
        ->and($step->name)->toBe('process');
});

it('can execute a pipeline', function () {
    $template1 = PromptTemplate::create([
        'name' => 'First',
        'slug' => 'first',
        'user_prompt' => 'Input: {{ text }}',
        'prompt_type_id' => $this->type->id,
    ]);

    $pipeline = PromptPipeline::create([
        'name' => 'Execute Test',
        'slug' => 'execute-test',
    ]);

    $pipeline->addStep('step1', $template1);

    $context = $pipeline->execute(['text' => 'Hello']);

    expect($context)->toBeInstanceOf(PipelineContext::class)
        ->and($context->getLastResult())->not->toBeNull()
        ->and($context->getLastResult()->userPrompt)->toBe('Input: Hello');
});

it('can chain multiple templates', function () {
    $template1 = PromptTemplate::create([
        'name' => 'Template 1',
        'slug' => 'template-1',
        'user_prompt' => 'Step 1: {{ input }}',
        'prompt_type_id' => $this->type->id,
    ]);

    $template2 = PromptTemplate::create([
        'name' => 'Template 2',
        'slug' => 'template-2',
        'user_prompt' => 'Step 2: {{ input }}',
        'prompt_type_id' => $this->type->id,
    ]);

    $executor = app(PipelineExecutor::class);
    $context = $executor->fromTemplates(
        ['template-1', 'template-2'],
        ['input' => 'test']
    );

    expect($context->getResults())->toHaveCount(2);
});

it('can build pipeline fluently', function () {
    $template = PromptTemplate::create([
        'name' => 'Builder Template',
        'slug' => 'builder-template',
        'user_prompt' => 'Built: {{ value }}',
        'prompt_type_id' => $this->type->id,
    ]);

    $context = PipelineBuilder::create('Fluent Pipeline')
        ->addTemplate($template, 'process')
        ->execute(['value' => 'success']);

    expect($context->getLastResult())->not->toBeNull()
        ->and($context->getLastResult()->userPrompt)->toBe('Built: success');
});

it('handles conditional steps', function () {
    $template = PromptTemplate::create([
        'name' => 'Conditional',
        'slug' => 'conditional',
        'user_prompt' => 'Conditional content',
        'prompt_type_id' => $this->type->id,
    ]);

    $pipeline = PromptPipeline::create([
        'name' => 'Conditional Pipeline',
        'slug' => 'conditional-pipeline',
    ]);

    $pipeline->steps()->create([
        'name' => 'conditional_step',
        'prompt_template_id' => $template->id,
        'order' => 0,
        'conditions' => [
            ['field' => 'enabled', 'operator' => '==', 'value' => true],
        ],
        'is_enabled' => true,
    ]);

    // Should execute when condition is met
    $context1 = $pipeline->execute(['enabled' => true]);
    expect($context1->getResults())->toHaveCount(1);

    // Should skip when condition is not met
    $context2 = $pipeline->execute(['enabled' => false]);
    expect($context2->getResults())->toHaveCount(0);
});

it('can duplicate a pipeline', function () {
    $pipeline = PromptPipeline::create([
        'name' => 'Original Pipeline',
        'slug' => 'original-pipeline',
    ]);

    $template = PromptTemplate::create([
        'name' => 'Pipeline Template',
        'slug' => 'pipeline-template',
        'user_prompt' => 'Content',
        'prompt_type_id' => $this->type->id,
    ]);

    $pipeline->addStep('step1', $template);

    $duplicate = $pipeline->duplicate('Copy Pipeline', 'copy-pipeline');

    expect($duplicate->name)->toBe('Copy Pipeline')
        ->and($duplicate->steps()->count())->toBe(1)
        ->and($duplicate->id)->not->toBe($pipeline->id);
});

it('validates pipeline input against schema', function () {
    $pipeline = PromptPipeline::create([
        'name' => 'Schema Pipeline',
        'slug' => 'schema-pipeline',
        'input_schema' => [
            'name' => ['type' => 'string', 'required' => true],
            'age' => ['type' => 'integer', 'required' => false],
        ],
    ]);

    expect($pipeline->validateInput(['name' => 'John']))->toBeTrue()
        ->and($pipeline->validateInput([]))->toBeFalse()
        ->and($pipeline->validateInput(['name' => 'John', 'age' => 30]))->toBeTrue();
});
