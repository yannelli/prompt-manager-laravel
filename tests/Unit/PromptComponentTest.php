<?php

declare(strict_types=1);

use PromptManager\PromptTemplates\Models\PromptComponent;
use PromptManager\PromptTemplates\Models\PromptTemplate;
use PromptManager\PromptTemplates\Models\PromptType;
use PromptManager\PromptTemplates\Services\ComponentManager;

beforeEach(function () {
    $this->type = PromptType::create([
        'name' => 'Default',
        'slug' => 'default',
        'handler_class' => \PromptManager\PromptTemplates\Handlers\DefaultTypeHandler::class,
        'is_system' => true,
        'is_active' => true,
    ]);

    $this->template = PromptTemplate::create([
        'name' => 'Test Template',
        'slug' => 'test-template',
        'user_prompt' => 'Main content',
        'prompt_type_id' => $this->type->id,
    ]);
});

it('can create a component', function () {
    $component = PromptComponent::create([
        'name' => 'Format Instructions',
        'slug' => 'format-instructions',
        'content' => 'Please format your response as JSON.',
        'type' => 'format',
        'is_global' => true,
    ]);

    expect($component)->toBeInstanceOf(PromptComponent::class)
        ->and($component->name)->toBe('Format Instructions')
        ->and($component->is_global)->toBeTrue();
});

it('can render a component with variables', function () {
    $component = PromptComponent::create([
        'name' => 'Greeting',
        'slug' => 'greeting',
        'content' => 'Hello, {{ name }}!',
    ]);

    $rendered = $component->render(['name' => 'World']);

    expect($rendered)->toBe('Hello, World!');
});

it('can attach component to template', function () {
    $component = PromptComponent::create([
        'name' => 'Footer',
        'slug' => 'footer',
        'content' => 'Thank you!',
    ]);

    $manager = app(ComponentManager::class);
    $manager->attach($this->template, $component, [
        'target' => 'user_prompt',
        'position' => 'append',
    ]);

    expect($this->template->components()->count())->toBe(1);
});

it('can enable and disable components', function () {
    $component = PromptComponent::create([
        'name' => 'Optional',
        'slug' => 'optional',
        'content' => 'Optional content',
    ]);

    $manager = app(ComponentManager::class);
    $manager->attach($this->template, $component, ['is_enabled' => true]);

    expect($manager->isEnabled($this->template, $component))->toBeTrue();

    $manager->disable($this->template, $component);
    expect($manager->isEnabled($this->template, $component))->toBeFalse();

    $manager->enable($this->template, $component);
    expect($manager->isEnabled($this->template, $component))->toBeTrue();
});

it('can toggle component state', function () {
    $component = PromptComponent::create([
        'name' => 'Toggle Test',
        'slug' => 'toggle-test',
        'content' => 'Toggle content',
    ]);

    $manager = app(ComponentManager::class);
    $manager->attach($this->template, $component, ['is_enabled' => true]);

    $newState = $manager->toggle($this->template, $component);
    expect($newState)->toBeFalse();

    $newState = $manager->toggle($this->template, $component);
    expect($newState)->toBeTrue();
});

it('renders template with enabled components', function () {
    $component = PromptComponent::create([
        'name' => 'Suffix',
        'slug' => 'suffix',
        'content' => 'Be concise.',
        'position' => 'append',
    ]);

    $manager = app(ComponentManager::class);
    $manager->attach($this->template, $component, [
        'target' => 'user_prompt',
        'position' => 'append',
        'is_enabled' => true,
    ]);

    $rendered = $this->template->render();

    expect($rendered->userPrompt)->toContain('Main content')
        ->and($rendered->userPrompt)->toContain('Be concise.');
});

it('extracts expected variables from content', function () {
    $component = PromptComponent::create([
        'name' => 'Variables Component',
        'slug' => 'variables-component',
        'content' => 'Hello {{ name }}, your score is {{ score }}.',
    ]);

    $expected = $component->getExpectedVariables();

    expect($expected)->toHaveKey('name')
        ->and($expected)->toHaveKey('score');
});
