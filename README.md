# Laravel Prompt Templates

A powerful Laravel package for managing prompt templates with versioning, components, types, and pipeline support. Designed for AI/LLM applications.

## Features

- **Prompt Templates** - Create and manage reusable prompt templates with variables
- **Version Control** - Track template versions with custom mapping logic for migrations
- **Customizable Types** - Define prompt types with handler classes for different rendering strategies
- **Components** - Reusable components that can be attached to templates (enable/disable per user)
- **Pipelines** - Chain templates together for complex workflows
- **Caching** - Built-in caching for rendered prompts

## Installation

```bash
composer require prompt-manager/laravel-prompt-templates
```

Publish and run migrations:

```bash
php artisan prompt-templates:install --migrate --seed
```

Or manually:

```bash
php artisan vendor:publish --tag="prompt-templates-config"
php artisan vendor:publish --tag="prompt-templates-migrations"
php artisan migrate
php artisan prompt-templates:seed-types
```

## Quick Start

### Creating a Template

```php
use PromptManager\PromptTemplates\Models\PromptTemplate;
use PromptManager\PromptTemplates\Models\PromptType;

// Get a prompt type
$chatType = PromptType::findBySlug('chat');

// Create a template
$template = PromptTemplate::create([
    'name' => 'Customer Support',
    'slug' => 'customer-support',
    'system_prompt' => 'You are a helpful customer support agent for {{ company }}.',
    'user_prompt' => '{{ user_message }}',
    'prompt_type_id' => $chatType->id,
    'variables' => [
        'company' => ['type' => 'string', 'required' => true],
        'user_message' => ['type' => 'string', 'required' => true],
    ],
]);
```

### Rendering a Template

```php
use PromptManager\PromptTemplates\Facades\PromptTemplate;

// Render by slug
$rendered = PromptTemplate::renderBySlug('customer-support', [
    'company' => 'Acme Inc',
    'user_message' => 'How do I reset my password?',
]);

// Access rendered content
echo $rendered->systemPrompt;  // "You are a helpful customer support agent for Acme Inc."
echo $rendered->userPrompt;    // "How do I reset my password?"

// Get as messages array for chat APIs
$messages = $rendered->toMessages();
// [
//     ['role' => 'system', 'content' => '...'],
//     ['role' => 'user', 'content' => '...'],
// ]
```

### Using Components

```php
use PromptManager\PromptTemplates\Models\PromptComponent;
use PromptManager\PromptTemplates\Services\ComponentManager;

// Create a reusable component
$component = PromptComponent::create([
    'name' => 'JSON Format',
    'slug' => 'json-format',
    'content' => 'Please format your response as valid JSON.',
    'position' => 'append',
    'is_global' => true,
]);

// Attach to a template
$manager = app(ComponentManager::class);
$manager->attach($template, $component, [
    'target' => 'system_prompt',
    'is_enabled' => true,
]);

// Toggle component for a specific user
$manager->disable($template, $component, userId: auth()->id());
$manager->enable($template, $component, userId: auth()->id());
```

### Version Control

```php
// Create a version snapshot
$template->createVersion('Added new instructions');

// Get version history
$versions = $template->versions()->orderByDesc('version')->get();

// Restore to a previous version
$template->restoreFromVersion(1);

// Custom version mapper
use PromptManager\PromptTemplates\Support\AbstractVersionMapper;

class V1ToV2Mapper extends AbstractVersionMapper
{
    protected int $sourceVersion = 1;
    protected int $targetVersion = 2;

    public function map($fromVersion, $toVersion, array $content): VersionMappingResult
    {
        // Rename deprecated field
        $content = $this->renameKey($content, 'old_field', 'new_field');

        return VersionMappingResult::success(
            content: $content,
            fromVersion: 1,
            toVersion: 2,
            transformations: ["Renamed 'old_field' to 'new_field'"]
        );
    }
}
```

### Pipelines

```php
use PromptManager\PromptTemplates\Facades\PromptPipeline;
use PromptManager\PromptTemplates\Pipelines\PipelineBuilder;

// Create a pipeline from templates
$pipeline = PipelineBuilder::create('Analysis Pipeline')
    ->addTemplate('extract-data', 'extract', [
        'input_mapping' => ['document' => 'raw_text'],
        'output_mapping' => ['extracted_data' => 'content'],
    ])
    ->addTemplate('analyze-data', 'analyze', [
        'input_mapping' => ['data' => 'extracted_data'],
    ])
    ->addTemplate('summarize', 'summarize')
    ->save();

// Execute the pipeline
$context = $pipeline->execute([
    'raw_text' => 'Document content here...',
]);

// Get results from each step
$extractResult = $context->getResult('extract');
$analyzeResult = $context->getResult('analyze');
$finalResult = $context->getLastResult();

// Or execute ad-hoc from templates
$context = PromptPipeline::fromTemplates(
    ['template-1', 'template-2', 'template-3'],
    ['input' => 'Initial data']
);
```

### Custom Type Handlers

```php
// Generate a new handler
php artisan prompt-templates:make-handler CustomHandler

// In app/PromptHandlers/CustomHandler.php
use PromptManager\PromptTemplates\Handlers\AbstractTypeHandler;

class CustomHandler extends AbstractTypeHandler
{
    public function __invoke(PromptTemplate $template, array $variables = [], array $options = []): RenderedPrompt
    {
        // Custom rendering logic
        $systemPrompt = $this->substituteVariables(
            $template->system_prompt,
            $variables,
            $template->getExpectedVariables()
        );

        // Add custom processing
        $systemPrompt = $this->addCustomInstructions($systemPrompt);

        return new RenderedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $this->substituteVariables($template->user_prompt, $variables),
            templateId: $template->uuid,
            version: $template->current_version,
        );
    }
}
```

Register in `config/prompt-templates.php`:

```php
'type_handlers' => [
    'custom' => \App\PromptHandlers\CustomHandler::class,
],
```

## Configuration

```php
// config/prompt-templates.php
return [
    // Customize table names
    'tables' => [
        'prompt_templates' => 'prompt_templates',
        // ...
    ],

    // Use custom models
    'models' => [
        'prompt_template' => \App\Models\MyPromptTemplate::class,
    ],

    // Register type handlers
    'type_handlers' => [
        'default' => DefaultTypeHandler::class,
        'chat' => ChatTypeHandler::class,
        'custom' => CustomHandler::class,
    ],

    // Variable delimiters (default: {{ }})
    'variable_delimiters' => [
        'start' => '{{',
        'end' => '}}',
    ],

    // Caching
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],

    // Pipeline settings
    'pipeline' => [
        'max_depth' => 10,
    ],
];
```

## Available Prompt Types

| Type | Handler | Description |
|------|---------|-------------|
| `default` | `DefaultTypeHandler` | Basic variable substitution |
| `chat` | `ChatTypeHandler` | Chat-style with messages array |
| `completion` | `CompletionTypeHandler` | Single content string |
| `instruction` | `InstructionTypeHandler` | Instruction/Input/Response format |

## API Reference

### PromptTemplate Model

```php
$template->render($variables, $options);     // Render with variables
$template->createVersion($changelog);         // Create version snapshot
$template->restoreFromVersion($version);      // Restore to version
$template->duplicate($name, $slug);           // Duplicate template
$template->getExpectedVariables();            // Get variable definitions
$template->validateVariables($variables);     // Validate variables
```

### RenderedPrompt DTO

```php
$rendered->systemPrompt;       // System prompt content
$rendered->userPrompt;         // User prompt content
$rendered->assistantPrompt;    // Assistant prompt content
$rendered->content;            // Raw content (completion style)
$rendered->messages;           // Messages array for chat APIs
$rendered->toMessages();       // Get as messages array
$rendered->toString();         // Get as single string
$rendered->metadata;           // Template metadata
$rendered->usedVariables;      // Variables that were substituted
```

### ComponentManager Service

```php
$manager->attach($template, $component, $options);
$manager->detach($template, $component, $userId);
$manager->enable($template, $component, $userId);
$manager->disable($template, $component, $userId);
$manager->toggle($template, $component, $userId);
$manager->isEnabled($template, $component, $userId);
$manager->getEnabled($template, $userId);
$manager->reorder($template, $componentIds, $userId);
```

### PipelineContext

```php
$context->get($key, $default);      // Get value from context
$context->set($key, $value);        // Set value in context
$context->all();                     // Get all context data
$context->getResult($stepName);      // Get step result
$context->getResults();              // Get all results
$context->getLastResult();           // Get final result
$context->hasErrors();               // Check for errors
$context->getErrors();               // Get error details
```

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
