<?php

declare(strict_types=1);

use PromptManager\PromptTemplates\Handlers\ChatTypeHandler;
use PromptManager\PromptTemplates\Handlers\CompletionTypeHandler;
use PromptManager\PromptTemplates\Handlers\DefaultTypeHandler;
use PromptManager\PromptTemplates\Handlers\InstructionTypeHandler;
use PromptManager\PromptTemplates\Models\PromptTemplate;
use PromptManager\PromptTemplates\Models\PromptType;

beforeEach(function () {
    $this->chatType = PromptType::create([
        'name' => 'Chat',
        'slug' => 'chat',
        'handler_class' => ChatTypeHandler::class,
        'is_system' => true,
        'is_active' => true,
    ]);

    $this->completionType = PromptType::create([
        'name' => 'Completion',
        'slug' => 'completion',
        'handler_class' => CompletionTypeHandler::class,
        'is_system' => true,
        'is_active' => true,
    ]);

    $this->instructionType = PromptType::create([
        'name' => 'Instruction',
        'slug' => 'instruction',
        'handler_class' => InstructionTypeHandler::class,
        'is_system' => true,
        'is_active' => true,
    ]);
});

it('renders chat-style prompts correctly', function () {
    $template = PromptTemplate::create([
        'name' => 'Chat Template',
        'slug' => 'chat-template',
        'system_prompt' => 'You are a helpful assistant.',
        'user_prompt' => 'Hello, {{ name }}!',
        'prompt_type_id' => $this->chatType->id,
    ]);

    $handler = new ChatTypeHandler;
    $result = $handler($template, ['name' => 'World']);

    expect($result->systemPrompt)->toBe('You are a helpful assistant.')
        ->and($result->userPrompt)->toBe('Hello, World!')
        ->and($result->messages)->toHaveCount(2)
        ->and($result->messages[0]['role'])->toBe('system')
        ->and($result->messages[1]['role'])->toBe('user');
});

it('includes conversation history in chat prompts', function () {
    $template = PromptTemplate::create([
        'name' => 'Chat With History',
        'slug' => 'chat-with-history',
        'system_prompt' => 'You are helpful.',
        'user_prompt' => 'Continue please.',
        'prompt_type_id' => $this->chatType->id,
    ]);

    $handler = new ChatTypeHandler;
    $result = $handler($template, [
        '_messages' => [
            ['role' => 'user', 'content' => 'Previous message'],
            ['role' => 'assistant', 'content' => 'Previous response'],
        ],
    ]);

    expect($result->messages)->toHaveCount(4);
});

it('renders completion-style prompts correctly', function () {
    $template = PromptTemplate::create([
        'name' => 'Completion Template',
        'slug' => 'completion-template',
        'content' => 'Complete this: {{ prefix }}',
        'prompt_type_id' => $this->completionType->id,
    ]);

    $handler = new CompletionTypeHandler;
    $result = $handler($template, ['prefix' => 'Once upon a time']);

    expect($result->content)->toBe('Complete this: Once upon a time');
});

it('renders instruction-style prompts correctly', function () {
    $template = PromptTemplate::create([
        'name' => 'Instruction Template',
        'slug' => 'instruction-template',
        'system_prompt' => 'Summarize the following text.',
        'user_prompt' => '{{ text }}',
        'prompt_type_id' => $this->instructionType->id,
    ]);

    $handler = new InstructionTypeHandler;
    $result = $handler($template, ['text' => 'Long article content here.']);

    expect($result->content)->toContain('### Instruction:')
        ->and($result->content)->toContain('### Input:')
        ->and($result->content)->toContain('### Response:')
        ->and($result->content)->toContain('Long article content here.');
});

it('validates chat template structure', function () {
    $validTemplate = PromptTemplate::create([
        'name' => 'Valid Chat',
        'slug' => 'valid-chat',
        'system_prompt' => 'You are helpful.',
        'user_prompt' => 'Hello!',
    ]);

    $invalidTemplate = PromptTemplate::create([
        'name' => 'Invalid Chat',
        'slug' => 'invalid-chat',
        'content' => 'Only content, no chat fields.',
    ]);

    $handler = new ChatTypeHandler;

    expect($handler->validate($validTemplate))->toBeTrue()
        ->and($handler->validate($invalidTemplate))->toBeFalse();
});

it('validates instruction template structure', function () {
    $validTemplate = PromptTemplate::create([
        'name' => 'Valid Instruction',
        'slug' => 'valid-instruction',
        'system_prompt' => 'Do this task.',
    ]);

    $invalidTemplate = PromptTemplate::create([
        'name' => 'Invalid Instruction',
        'slug' => 'invalid-instruction',
        'user_prompt' => 'No instruction.',
    ]);

    $handler = new InstructionTypeHandler;

    expect($handler->validate($validTemplate))->toBeTrue()
        ->and($handler->validate($invalidTemplate))->toBeFalse();
});

it('supports custom configuration', function () {
    $handler = new InstructionTypeHandler;
    $handler->setConfig([
        'instruction_prefix' => '## Task:\n',
        'response_prefix' => '## Answer:\n',
    ]);

    $template = PromptTemplate::create([
        'name' => 'Custom Instruction',
        'slug' => 'custom-instruction',
        'system_prompt' => 'Custom task.',
    ]);

    $result = $handler($template, []);

    expect($result->content)->toContain('## Task:')
        ->and($result->content)->toContain('## Answer:');
});

it('tracks used variables', function () {
    $template = PromptTemplate::create([
        'name' => 'Track Variables',
        'slug' => 'track-variables',
        'user_prompt' => 'Hello {{ name }}, you are {{ age }} years old.',
    ]);

    $handler = new DefaultTypeHandler;
    $result = $handler($template, ['name' => 'John', 'age' => 30, 'unused' => 'value']);

    expect($result->usedVariables)->toHaveKey('name')
        ->and($result->usedVariables)->toHaveKey('age')
        ->and($result->usedVariables)->not->toHaveKey('unused');
});
