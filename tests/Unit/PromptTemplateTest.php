<?php

declare(strict_types=1);

use PromptManager\PromptTemplates\Models\PromptTemplate;
use PromptManager\PromptTemplates\Models\PromptType;

beforeEach(function () {
    $this->type = PromptType::create([
        'name' => 'Default',
        'slug' => 'default',
        'handler_class' => \PromptManager\PromptTemplates\Handlers\DefaultTypeHandler::class,
        'is_system' => true,
        'is_active' => true,
    ]);
});

it('can create a prompt template', function () {
    $template = PromptTemplate::create([
        'name' => 'Test Template',
        'slug' => 'test-template',
        'system_prompt' => 'You are a helpful assistant.',
        'user_prompt' => 'Hello, {{ name }}!',
        'prompt_type_id' => $this->type->id,
    ]);

    expect($template)->toBeInstanceOf(PromptTemplate::class)
        ->and($template->name)->toBe('Test Template')
        ->and($template->slug)->toBe('test-template')
        ->and($template->uuid)->not->toBeNull();
});

it('can render a template with variables', function () {
    $template = PromptTemplate::create([
        'name' => 'Greeting Template',
        'slug' => 'greeting',
        'user_prompt' => 'Hello, {{ name }}! Welcome to {{ place }}.',
        'prompt_type_id' => $this->type->id,
    ]);

    $rendered = $template->render([
        'name' => 'John',
        'place' => 'Laravel',
    ]);

    expect($rendered->userPrompt)->toBe('Hello, John! Welcome to Laravel.');
});

it('can create versions', function () {
    $template = PromptTemplate::create([
        'name' => 'Versioned Template',
        'slug' => 'versioned',
        'user_prompt' => 'Version 1 content',
    ]);

    expect($template->current_version)->toBe(1);

    $version = $template->createVersion('Initial version');

    expect($version->version)->toBe(1)
        ->and($template->fresh()->current_version)->toBe(2);
});

it('can restore from a version', function () {
    $template = PromptTemplate::create([
        'name' => 'Restore Test',
        'slug' => 'restore-test',
        'user_prompt' => 'Original content',
    ]);

    $template->createVersion('Version 1');

    $template->user_prompt = 'Updated content';
    $template->save();

    expect($template->user_prompt)->toBe('Updated content');

    $template->restoreFromVersion(1);

    expect($template->fresh()->user_prompt)->toBe('Original content');
});

it('can duplicate a template', function () {
    $template = PromptTemplate::create([
        'name' => 'Original',
        'slug' => 'original',
        'user_prompt' => 'Original content',
    ]);

    $duplicate = $template->duplicate('Duplicate', 'duplicate');

    expect($duplicate->name)->toBe('Duplicate')
        ->and($duplicate->slug)->toBe('duplicate')
        ->and($duplicate->user_prompt)->toBe('Original content')
        ->and($duplicate->id)->not->toBe($template->id);
});

it('validates expected variables', function () {
    $template = PromptTemplate::create([
        'name' => 'Variables Test',
        'slug' => 'variables-test',
        'user_prompt' => 'Hello, {{ name }}!',
        'variables' => [
            'name' => [
                'type' => 'string',
                'required' => true,
            ],
        ],
    ]);

    expect($template->validateVariables(['name' => 'John']))->toBeTrue()
        ->and($template->validateVariables([]))->toBeFalse();
});
