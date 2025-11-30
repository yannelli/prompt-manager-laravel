[![Tests](https://github.com/yannelli/prompt-manager-laravel/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/yannelli/prompt-manager-laravel/actions/workflows/tests.yml)

# Prompt Manager for Laravel

A powerful Laravel package for managing prompt templates with versioning, component toggling, chaining, and customizable type handlers.

## Features

- **Template Management** - Create, update, and organize prompt templates with slugs and metadata
- **Version Control** - Full versioning support with custom mapping logic for backward compatibility
- **Component System** - Toggleable template components (prepend, append, replace) with conditional display
- **Type System** - Extensible prompt types (system, user, assistant, tool, custom) with invokable handlers
- **Pipeline/Chaining** - Chain multiple templates together, passing results through each step
- **Multiple Renderers** - Simple variable substitution or full Blade template support
- **Execution Tracking** - Optional logging of prompt executions with performance metrics

## Requirements

- PHP 8.2+
- Laravel 11.0 or 12.0

## Installation

```bash
composer require yannelli/prompt-manager
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="prompt-manager-config"
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Start

### Creating a Template

```php
use Yannelli\PromptManager\Facades\PromptManager;

$template = PromptManager::create([
    'slug' => 'greeting',
    'name' => 'Greeting Prompt',
    'type' => 'user',
    'content' => 'Hello, {{ name }}! Welcome to {{ app_name }}.',
]);
```

### Rendering a Template

```php
$result = PromptManager::render('greeting', [
    'variables' => [
        'name' => 'John',
        'app_name' => 'My Application',
    ],
]);

echo $result->content; // "Hello, John! Welcome to My Application."
echo $result->role;    // "user"
```

### Using the Pipeline for Chaining

```php
use Yannelli\PromptManager\DTOs\PromptContext;

$messages = PromptManager::chain()
    ->template('system-prompt')
    ->template('user-query', ['query' => 'What is Laravel?'])
    ->template('format-instructions')
    ->toMessages(PromptContext::make([
        'variables' => ['language' => 'English'],
    ]));

// Result:
// [
//     ['role' => 'system', 'content' => '...'],
//     ['role' => 'user', 'content' => '...'],
//     ['role' => 'user', 'content' => '...'],
// ]
```

## Template Versions

### Creating New Versions

```php
$template = PromptManager::template('greeting');

$template->createVersion('Updated greeting: Hello {{ name }}!', [
    'change_summary' => 'Simplified greeting message',
    'variables' => ['name'],
]);
```

### Requesting Specific Versions

```php
$result = PromptManager::render('greeting', [
    'version' => 1, // Use version 1 instead of current
]);
```

### Version Mapping

Map client versions to template versions for backward compatibility:

```php
$result = PromptManager::render('api-prompt', [
    'variables' => ['query' => 'test'],
    'metadata' => [
        'version_mapping' => [
            'client_version' => '1.5.0',
            'rules' => [
                ['client_version' => '>=2.0', 'template_version' => 3],
                ['client_version' => '>=1.0', 'template_version' => 2],
                ['client_version' => '*', 'template_version' => 1],
            ],
        ],
    ],
]);
```

## Components

Components are reusable template parts that can be toggled on/off.

### Creating Templates with Components

```php
$template = PromptManager::create([
    'slug' => 'assistant',
    'name' => 'AI Assistant',
    'content' => 'You are a helpful assistant.',
    'components' => [
        [
            'key' => 'code_guidelines',
            'name' => 'Code Guidelines',
            'content' => 'When writing code, follow best practices...',
            'position' => 'append',
            'is_default_enabled' => true,
        ],
        [
            'key' => 'verbose_mode',
            'name' => 'Verbose Mode',
            'content' => 'Provide detailed explanations...',
            'position' => 'append',
            'is_default_enabled' => false,
        ],
    ],
]);
```

### Toggling Components

```php
// Enable specific components
$result = PromptManager::render('assistant', [
    'enabled_components' => ['verbose_mode'],
]);

// Disable specific components
$result = PromptManager::render('assistant', [
    'disabled_components' => ['code_guidelines'],
]);
```

### Conditional Components

Components can be shown/hidden based on context:

```php
$template->addComponent([
    'key' => 'admin_tools',
    'content' => 'Admin-only instructions...',
    'conditions' => [
        ['field' => 'user_role', 'operator' => '=', 'value' => 'admin'],
    ],
]);

// Component only shows if user_role is 'admin'
$result = PromptManager::render('assistant', [
    'variables' => ['user_role' => 'admin'],
]);
```

## Custom Prompt Types

### Registering a Custom Type

```php
use Yannelli\PromptManager\Types\BasePromptType;

class ToolPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'tool';
    }

    public function prepareContext(PromptContext $context): PromptContext
    {
        return $context->withVariables([
            'tool_version' => config('app.tool_version'),
        ]);
    }

    public function postProcess(RenderResult $result): RenderResult
    {
        return $result->withMetadata(['is_tool_call' => true]);
    }
}

// Register the type
PromptManager::registerType('tool', ToolPromptType::class);
```

Or add to config:

```php
// config/prompt-manager.php
'types' => [
    'system' => \Yannelli\PromptManager\Types\SystemPromptType::class,
    'user' => \Yannelli\PromptManager\Types\UserPromptType::class,
    'assistant' => \Yannelli\PromptManager\Types\AssistantPromptType::class,
    'tool' => \App\PromptTypes\ToolPromptType::class,
],
```

## Pipeline Pipes

Add custom processing to the pipeline:

```php
use Yannelli\PromptManager\Pipelines\Pipes\SanitizeVariablesPipe;
use Yannelli\PromptManager\Pipelines\Pipes\ValidateVariablesPipe;

$result = PromptManager::chain()
    ->pipe(new SanitizeVariablesPipe(escapeHtml: true))
    ->pipe(new ValidateVariablesPipe(['query'], strict: true))
    ->template('search-prompt')
    ->run($context);
```

## Action Classes

Use action classes for more control:

```php
use Yannelli\PromptManager\Actions\RenderPromptAction;
use Yannelli\PromptManager\Actions\CreateTemplateAction;
use Yannelli\PromptManager\Actions\DuplicateTemplateAction;
use Yannelli\PromptManager\Actions\ExportTemplateAction;
use Yannelli\PromptManager\Actions\ImportTemplateAction;

// Create
$template = app(CreateTemplateAction::class)->handle($data);

// Duplicate
$copy = app(DuplicateTemplateAction::class)->handle('original-slug', 'new-slug');

// Export/Import
$exported = app(ExportTemplateAction::class)->handle('my-template', includeAllVersions: true);
$imported = app(ImportTemplateAction::class)->handle($exported, overwrite: true);
```

## Configuration

```php
// config/prompt-manager.php

return [
    // Default prompt type
    'default_type_key' => 'user',

    // Registered types
    'types' => [
        'system' => SystemPromptType::class,
        'user' => UserPromptType::class,
        'assistant' => AssistantPromptType::class,
    ],

    // Template renderer
    'renderer' => 'simple', // 'simple' or 'blade'

    // Version settings
    'versioning' => [
        'auto_publish' => false,
        'keep_versions' => null, // null = keep all
    ],

    // Database tables
    'tables' => [
        'templates' => 'prompt_templates',
        'versions' => 'prompt_template_versions',
        'components' => 'prompt_components',
        'executions' => 'prompt_executions',
    ],

    // Execution tracking
    'track_executions' => false,

    // Cache settings
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'prompt_manager',
    ],
];
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

Learn more about our mission:
Author: [Ryan Yannelli](https://ryanyannelli.com)
Sponsor: [Nextvisit AI Medical Scribe](https://nextvisit.ai)
