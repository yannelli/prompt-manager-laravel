# Prompt Manager Laravel Package Design

A Laravel 12 package for managing prompt templates with versioning, component toggling, chaining, and customizable type handlers.

## Package Overview

**Package Name:** `prompt-manager`
**Namespace:** `Yannelli\PromptManager`
**Laravel Version:** 12.x
**PHP Version:** 8.2+

---

## 1. Directory Structure

```
prompt-manager/
├── .github/
│   └── workflows/
│       └── tests.yml
├── config/
│   └── prompt-manager.php
├── database/
│   ├── migrations/
│   │   ├── create_prompt_templates_table.php
│   │   ├── create_prompt_template_versions_table.php
│   │   ├── create_prompt_components_table.php
│   │   └── create_prompt_executions_table.php
│   └── factories/
│       ├── PromptTemplateFactory.php
│       └── PromptComponentFactory.php
├── src/
│   ├── Actions/
│   │   ├── RenderPromptAction.php
│   │   ├── CreateTemplateVersionAction.php
│   │   └── ResolveVersionAction.php
│   ├── Contracts/
│   │   ├── PromptRendererInterface.php
│   │   ├── PromptTypeInterface.php
│   │   └── VersionResolverInterface.php
│   ├── DTOs/
│   │   ├── PromptContext.php
│   │   ├── RenderResult.php
│   │   └── TemplateData.php
│   ├── Enums/
│   │   ├── PromptRole.php
│   │   └── ComponentStatus.php
│   ├── Exceptions/
│   │   ├── PromptManagerException.php
│   │   ├── TemplateNotFoundException.php
│   │   └── RenderingException.php
│   ├── Facades/
│   │   └── PromptManager.php
│   ├── Models/
│   │   ├── PromptTemplate.php
│   │   ├── PromptTemplateVersion.php
│   │   └── PromptComponent.php
│   ├── Pipelines/
│   │   ├── PromptPipeline.php
│   │   └── Pipes/
│   │       ├── ResolveVariablesePipe.php
│   │       ├── ApplyComponentTogglesPipe.php
│   │       ├── RenderTemplatePipe.php
│   │       ├── ValidateOutputPipe.php
│   │       └── TransformOutputPipe.php
│   ├── Renderers/
│   │   ├── BladeRenderer.php
│   │   ├── TwigRenderer.php (optional)
│   │   └── SimpleRenderer.php
│   ├── Types/
│   │   ├── BasePromptType.php
│   │   ├── SystemPromptType.php
│   │   ├── UserPromptType.php
│   │   ├── AssistantPromptType.php
│   │   └── CustomPromptType.php
│   ├── Versioning/
│   │   ├── VersionManager.php
│   │   ├── Strategies/
│   │   │   ├── LatestVersionStrategy.php
│   │   │   ├── SpecificVersionStrategy.php
│   │   │   └── MappedVersionStrategy.php
│   │   └── Mappers/
│   │       └── VersionMapper.php
│   ├── PromptManager.php
│   └── PromptManagerServiceProvider.php
├── tests/
│   ├── Unit/
│   │   ├── RenderPromptActionTest.php
│   │   ├── VersionManagerTest.php
│   │   └── PipelineTest.php
│   └── Feature/
│       ├── PromptTemplateTest.php
│       └── ChainedPromptTest.php
├── composer.json
├── phpunit.xml.dist
└── README.md
```

---

## 2. Database Schema

### 2.1 `prompt_templates` Table

The main template registry.

```php
Schema::create('prompt_templates', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->string('slug')->unique();              // human-readable identifier
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('type');                        // maps to Type class
    $table->json('metadata')->nullable();          // flexible metadata
    $table->boolean('is_active')->default(true);
    $table->unsignedBigInteger('current_version_id')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['slug', 'is_active']);
    $table->index('type');
});
```

### 2.2 `prompt_template_versions` Table

Stores all versions with full content (snapshot strategy).

```php
Schema::create('prompt_template_versions', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('prompt_template_id')
          ->constrained()
          ->cascadeOnDelete();
    $table->unsignedInteger('version_number');
    $table->text('content');                       // the actual template content
    $table->json('variables')->nullable();         // expected variables schema
    $table->json('component_config')->nullable();  // component defaults
    $table->json('mapping_rules')->nullable();     // custom logic for version mapping
    $table->string('change_summary')->nullable();
    $table->unsignedBigInteger('created_by')->nullable();
    $table->boolean('is_published')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    $table->unique(['prompt_template_id', 'version_number']);
    $table->index('is_published');
});
```

### 2.3 `prompt_components` Table

Optional components that can be toggled on/off.

```php
Schema::create('prompt_components', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('prompt_template_id')
          ->constrained()
          ->cascadeOnDelete();
    $table->string('key');                         // component identifier
    $table->string('name');
    $table->text('content');
    $table->string('position')->default('append'); // prepend, append, replace:{marker}
    $table->integer('order')->default(0);
    $table->boolean('is_default_enabled')->default(true);
    $table->json('conditions')->nullable();        // conditional display rules
    $table->timestamps();

    $table->unique(['prompt_template_id', 'key']);
    $table->index(['prompt_template_id', 'order']);
});
```

### 2.4 `prompt_executions` Table (Optional - for tracking/debugging)

```php
Schema::create('prompt_executions', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('prompt_template_version_id')
          ->constrained('prompt_template_versions')
          ->cascadeOnDelete();
    $table->json('input_variables');
    $table->json('enabled_components')->nullable();
    $table->text('rendered_output');
    $table->json('pipeline_chain')->nullable();    // if part of a chain
    $table->unsignedInteger('execution_time_ms')->nullable();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->timestamps();

    $table->index('created_at');
});
```

---

## 3. Core Models

### 3.1 PromptTemplate Model

```php
<?php

namespace Yannelli\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Yannelli\PromptManager\Contracts\PromptTypeInterface;

class PromptTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'type',
        'metadata',
        'is_active',
        'current_version_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    // Relationships
    public function versions(): HasMany
    {
        return $this->hasMany(PromptTemplateVersion::class)
                    ->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PromptTemplateVersion::class, 'current_version_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(PromptComponent::class)->orderBy('order');
    }

    // Accessors
    public function getTypeHandlerAttribute(): PromptTypeInterface
    {
        return app()->make(
            config("prompt-manager.types.{$this->type}", config('prompt-manager.default_type'))
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Methods
    public function createVersion(string $content, array $options = []): PromptTemplateVersion
    {
        $latestVersion = $this->versions()->max('version_number') ?? 0;

        $version = $this->versions()->create([
            'version_number' => $latestVersion + 1,
            'content' => $content,
            'variables' => $options['variables'] ?? null,
            'component_config' => $options['component_config'] ?? null,
            'mapping_rules' => $options['mapping_rules'] ?? null,
            'change_summary' => $options['change_summary'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ]);

        if ($options['set_as_current'] ?? true) {
            $this->update(['current_version_id' => $version->id]);
        }

        return $version;
    }

    public function getVersion(?int $versionNumber = null): ?PromptTemplateVersion
    {
        if ($versionNumber === null) {
            return $this->currentVersion;
        }

        return $this->versions()->where('version_number', $versionNumber)->first();
    }
}
```

### 3.2 PromptTemplateVersion Model

```php
<?php

namespace Yannelli\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PromptTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_template_id',
        'version_number',
        'content',
        'variables',
        'component_config',
        'mapping_rules',
        'change_summary',
        'created_by',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'component_config' => 'array',
        'mapping_rules' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PromptExecution::class);
    }

    public function publish(): self
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return $this;
    }

    public function getPreviousVersion(): ?self
    {
        return static::where('prompt_template_id', $this->prompt_template_id)
                     ->where('version_number', '<', $this->version_number)
                     ->orderByDesc('version_number')
                     ->first();
    }

    public function diff(?self $compareWith = null): array
    {
        $compareWith = $compareWith ?? $this->getPreviousVersion();

        if (!$compareWith) {
            return ['added' => $this->content, 'removed' => ''];
        }

        // Simple diff - can be enhanced with actual diff algorithm
        return [
            'old' => $compareWith->content,
            'new' => $this->content,
            'variables_changed' => $this->variables !== $compareWith->variables,
        ];
    }
}
```

### 3.3 PromptComponent Model

```php
<?php

namespace Yannelli\PromptManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PromptComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_template_id',
        'key',
        'name',
        'content',
        'position',
        'order',
        'is_default_enabled',
        'conditions',
    ];

    protected $casts = [
        'is_default_enabled' => 'boolean',
        'conditions' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    public function shouldBeEnabled(array $context = []): bool
    {
        if (empty($this->conditions)) {
            return $this->is_default_enabled;
        }

        // Evaluate conditions against context
        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field || !isset($context[$field])) {
            return true;
        }

        return match ($operator) {
            '=' => $context[$field] == $value,
            '!=' => $context[$field] != $value,
            '>' => $context[$field] > $value,
            '<' => $context[$field] < $value,
            'in' => in_array($context[$field], (array) $value),
            'not_in' => !in_array($context[$field], (array) $value),
            'contains' => str_contains($context[$field], $value),
            default => true,
        };
    }
}
```

---

## 4. DTOs (Data Transfer Objects)

### 4.1 PromptContext DTO

```php
<?php

namespace Yannelli\PromptManager\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class PromptContext implements Arrayable
{
    public function __construct(
        public array $variables = [],
        public array $enabledComponents = [],
        public array $disabledComponents = [],
        public ?int $version = null,
        public array $metadata = [],
        public ?string $previousResult = null, // For chaining
    ) {}

    public static function make(array $data = []): self
    {
        return new self(
            variables: $data['variables'] ?? [],
            enabledComponents: $data['enabled_components'] ?? [],
            disabledComponents: $data['disabled_components'] ?? [],
            version: $data['version'] ?? null,
            metadata: $data['metadata'] ?? [],
            previousResult: $data['previous_result'] ?? null,
        );
    }

    public function withVariables(array $variables): self
    {
        return new self(
            variables: array_merge($this->variables, $variables),
            enabledComponents: $this->enabledComponents,
            disabledComponents: $this->disabledComponents,
            version: $this->version,
            metadata: $this->metadata,
            previousResult: $this->previousResult,
        );
    }

    public function withPreviousResult(string $result): self
    {
        return new self(
            variables: $this->variables,
            enabledComponents: $this->enabledComponents,
            disabledComponents: $this->disabledComponents,
            version: $this->version,
            metadata: $this->metadata,
            previousResult: $result,
        );
    }

    public function toArray(): array
    {
        return [
            'variables' => $this->variables,
            'enabled_components' => $this->enabledComponents,
            'disabled_components' => $this->disabledComponents,
            'version' => $this->version,
            'metadata' => $this->metadata,
            'previous_result' => $this->previousResult,
        ];
    }
}
```

### 4.2 RenderResult DTO

```php
<?php

namespace Yannelli\PromptManager\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class RenderResult implements Arrayable
{
    public function __construct(
        public string $content,
        public string $role,
        public array $metadata = [],
        public ?string $templateSlug = null,
        public ?int $versionNumber = null,
        public array $usedComponents = [],
    ) {}

    public static function make(string $content, string $role, array $options = []): self
    {
        return new self(
            content: $content,
            role: $role,
            metadata: $options['metadata'] ?? [],
            templateSlug: $options['template_slug'] ?? null,
            versionNumber: $options['version_number'] ?? null,
            usedComponents: $options['used_components'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'role' => $this->role,
            'metadata' => $this->metadata,
            'template_slug' => $this->templateSlug,
            'version_number' => $this->versionNumber,
            'used_components' => $this->usedComponents,
        ];
    }

    public function toMessage(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}
```

---

## 5. Contracts (Interfaces)

### 5.1 PromptTypeInterface

```php
<?php

namespace Yannelli\PromptManager\Contracts;

use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Models\PromptTemplate;

interface PromptTypeInterface
{
    /**
     * Get the role for this prompt type (system, user, assistant, etc.)
     */
    public function getRole(): string;

    /**
     * Render the template with the given context
     */
    public function render(PromptTemplate $template, PromptContext $context): RenderResult;

    /**
     * Pre-process context before rendering
     */
    public function prepareContext(PromptContext $context): PromptContext;

    /**
     * Post-process the rendered result
     */
    public function postProcess(RenderResult $result): RenderResult;

    /**
     * Validate the context for this type
     */
    public function validateContext(PromptContext $context): array;
}
```

### 5.2 PromptRendererInterface

```php
<?php

namespace Yannelli\PromptManager\Contracts;

interface PromptRendererInterface
{
    /**
     * Render a template string with variables
     */
    public function render(string $template, array $variables = []): string;

    /**
     * Check if the renderer supports a given template
     */
    public function supports(string $template): bool;
}
```

### 5.3 VersionResolverInterface

```php
<?php

namespace Yannelli\PromptManager\Contracts;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;

interface VersionResolverInterface
{
    /**
     * Resolve the appropriate version for the given context
     */
    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion;
}
```

---

## 6. Prompt Types (Invokable Classes)

### 6.1 BasePromptType

```php
<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\Contracts\PromptTypeInterface;
use Yannelli\PromptManager\Contracts\PromptRendererInterface;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Versioning\VersionManager;

abstract class BasePromptType implements PromptTypeInterface
{
    public function __construct(
        protected PromptRendererInterface $renderer,
        protected VersionManager $versionManager,
    ) {}

    abstract public function getRole(): string;

    public function __invoke(PromptTemplate $template, PromptContext $context): RenderResult
    {
        return $this->render($template, $context);
    }

    public function render(PromptTemplate $template, PromptContext $context): RenderResult
    {
        // Prepare context
        $context = $this->prepareContext($context);

        // Validate
        $errors = $this->validateContext($context);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid context: ' . implode(', ', $errors));
        }

        // Resolve version
        $version = $this->versionManager->resolve($template, $context);

        // Get content with components
        $content = $this->buildContent($template, $version, $context);

        // Render
        $rendered = $this->renderer->render($content, $context->variables);

        // Build result
        $result = RenderResult::make($rendered, $this->getRole(), [
            'template_slug' => $template->slug,
            'version_number' => $version->version_number,
            'used_components' => $this->getUsedComponents($template, $context),
        ]);

        // Post-process
        return $this->postProcess($result);
    }

    public function prepareContext(PromptContext $context): PromptContext
    {
        // Default: inject previous result as a variable if chaining
        if ($context->previousResult !== null) {
            return $context->withVariables([
                'previous_result' => $context->previousResult,
            ]);
        }

        return $context;
    }

    public function postProcess(RenderResult $result): RenderResult
    {
        return $result;
    }

    public function validateContext(PromptContext $context): array
    {
        return [];
    }

    protected function buildContent(
        PromptTemplate $template,
        PromptTemplateVersion $version,
        PromptContext $context
    ): string {
        $content = $version->content;
        $components = $template->components;

        foreach ($components as $component) {
            if (!$this->isComponentEnabled($component, $context)) {
                continue;
            }

            $content = match ($component->position) {
                'prepend' => $component->content . "\n\n" . $content,
                'append' => $content . "\n\n" . $component->content,
                default => $this->replaceMarker($content, $component),
            };
        }

        return $content;
    }

    protected function isComponentEnabled($component, PromptContext $context): bool
    {
        // Explicit disable takes precedence
        if (in_array($component->key, $context->disabledComponents)) {
            return false;
        }

        // Explicit enable
        if (in_array($component->key, $context->enabledComponents)) {
            return true;
        }

        // Fall back to component's own logic
        return $component->shouldBeEnabled($context->variables);
    }

    protected function replaceMarker(string $content, $component): string
    {
        if (str_starts_with($component->position, 'replace:')) {
            $marker = substr($component->position, 8);
            return str_replace("{{$marker}}", $component->content, $content);
        }

        return $content;
    }

    protected function getUsedComponents(PromptTemplate $template, PromptContext $context): array
    {
        return $template->components
            ->filter(fn ($c) => $this->isComponentEnabled($c, $context))
            ->pluck('key')
            ->toArray();
    }
}
```

### 6.2 SystemPromptType

```php
<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;

class SystemPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'system';
    }

    public function prepareContext(PromptContext $context): PromptContext
    {
        // System prompts might inject current date/time, environment info
        return $context->withVariables([
            'current_date' => now()->toDateString(),
            'current_time' => now()->toTimeString(),
        ]);
    }
}
```

### 6.3 UserPromptType

```php
<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\DTOs\PromptContext;

class UserPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'user';
    }

    public function validateContext(PromptContext $context): array
    {
        $errors = [];

        // Example: User prompts might require certain variables
        if (empty($context->variables['user_input'] ?? null) &&
            $context->previousResult === null) {
            $errors[] = 'User prompt requires user_input variable or previous result';
        }

        return $errors;
    }
}
```

### 6.4 AssistantPromptType

```php
<?php

namespace Yannelli\PromptManager\Types;

class AssistantPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'assistant';
    }
}
```

---

## 7. Pipeline System for Chaining

### 7.1 PromptPipeline

```php
<?php

namespace Yannelli\PromptManager\Pipelines;

use Illuminate\Pipeline\Pipeline;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Models\PromptTemplate;

class PromptPipeline
{
    protected array $templates = [];
    protected array $pipes = [];
    protected Pipeline $pipeline;

    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Add a template to the chain
     */
    public function template(string|PromptTemplate $template, array $contextOverrides = []): self
    {
        $this->templates[] = [
            'template' => $template,
            'context_overrides' => $contextOverrides,
        ];

        return $this;
    }

    /**
     * Add a custom pipe to the chain
     */
    public function pipe(callable|string $pipe): self
    {
        $this->pipes[] = $pipe;

        return $this;
    }

    /**
     * Execute the pipeline
     *
     * @return RenderResult|array<RenderResult>
     */
    public function run(PromptContext $context, bool $collectAll = false): RenderResult|array
    {
        $results = [];
        $currentContext = $context;

        foreach ($this->templates as $index => $item) {
            $template = $item['template'];

            if (is_string($template)) {
                $template = PromptTemplate::where('slug', $template)->firstOrFail();
            }

            // Apply context overrides for this specific template
            $templateContext = $currentContext;
            if (!empty($item['context_overrides'])) {
                $templateContext = $currentContext->withVariables($item['context_overrides']);
            }

            // Run through custom pipes if any
            if (!empty($this->pipes)) {
                $templateContext = $this->pipeline
                    ->send($templateContext)
                    ->through($this->pipes)
                    ->thenReturn();
            }

            // Render
            $result = $template->typeHandler->render($template, $templateContext);
            $results[] = $result;

            // Pass result to next iteration
            $currentContext = $currentContext->withPreviousResult($result->content);
        }

        return $collectAll ? $results : end($results);
    }

    /**
     * Run pipeline and return all results as messages array
     */
    public function toMessages(PromptContext $context): array
    {
        $results = $this->run($context, collectAll: true);

        return array_map(fn (RenderResult $r) => $r->toMessage(), $results);
    }
}
```

### 7.2 Example Pipes

```php
<?php

namespace Yannelli\PromptManager\Pipelines\Pipes;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;

class SanitizeVariablesPipe
{
    public function handle(PromptContext $context, Closure $next): PromptContext
    {
        $sanitized = array_map(function ($value) {
            if (is_string($value)) {
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $context->variables);

        return $next($context->withVariables($sanitized));
    }
}
```

```php
<?php

namespace Yannelli\PromptManager\Pipelines\Pipes;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;

class InjectUserPreferencesPipe
{
    public function __construct(
        protected ?int $userId = null
    ) {}

    public function handle(PromptContext $context, Closure $next): PromptContext
    {
        // Load user preferences and inject into context
        // This is where custom logic can modify component toggles based on user prefs

        $preferences = $this->loadUserPreferences();

        return $next($context->withVariables([
            'user_preferences' => $preferences,
        ]));
    }

    protected function loadUserPreferences(): array
    {
        // Load from database, cache, etc.
        return [];
    }
}
```

---

## 8. Version Management

### 8.1 VersionManager

```php
<?php

namespace Yannelli\PromptManager\Versioning;

use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Versioning\Strategies\LatestVersionStrategy;
use Yannelli\PromptManager\Versioning\Strategies\SpecificVersionStrategy;
use Yannelli\PromptManager\Versioning\Strategies\MappedVersionStrategy;

class VersionManager implements VersionResolverInterface
{
    protected array $strategies;

    public function __construct()
    {
        $this->strategies = [
            'specific' => new SpecificVersionStrategy(),
            'mapped' => new MappedVersionStrategy(),
            'latest' => new LatestVersionStrategy(),
        ];
    }

    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion
    {
        // If specific version requested, use that
        if ($context->version !== null) {
            return $this->strategies['specific']->resolve($template, $context);
        }

        // Check for mapping rules in metadata
        if (!empty($context->metadata['version_mapping'])) {
            return $this->strategies['mapped']->resolve($template, $context);
        }

        // Default to current/latest
        return $this->strategies['latest']->resolve($template, $context);
    }

    public function addStrategy(string $name, VersionResolverInterface $strategy): void
    {
        $this->strategies[$name] = $strategy;
    }
}
```

### 8.2 MappedVersionStrategy (Custom Logic)

```php
<?php

namespace Yannelli\PromptManager\Versioning\Strategies;

use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;

class MappedVersionStrategy implements VersionResolverInterface
{
    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion
    {
        $mapping = $context->metadata['version_mapping'];

        // Example mapping format:
        // [
        //     'client_version' => '1.0',
        //     'rules' => [
        //         ['client_version' => '>=2.0', 'template_version' => 3],
        //         ['client_version' => '>=1.5', 'template_version' => 2],
        //         ['client_version' => '*', 'template_version' => 1],
        //     ]
        // ]

        $clientVersion = $mapping['client_version'] ?? '*';
        $rules = $mapping['rules'] ?? [];

        foreach ($rules as $rule) {
            if ($this->matchesRule($clientVersion, $rule['client_version'])) {
                $version = $template->getVersion($rule['template_version']);
                if ($version) {
                    return $version;
                }
            }
        }

        // Fallback to current version
        return $template->currentVersion;
    }

    protected function matchesRule(string $clientVersion, string $rulePattern): bool
    {
        if ($rulePattern === '*') {
            return true;
        }

        if (str_starts_with($rulePattern, '>=')) {
            return version_compare($clientVersion, substr($rulePattern, 2), '>=');
        }

        if (str_starts_with($rulePattern, '>')) {
            return version_compare($clientVersion, substr($rulePattern, 1), '>');
        }

        if (str_starts_with($rulePattern, '<=')) {
            return version_compare($clientVersion, substr($rulePattern, 2), '<=');
        }

        if (str_starts_with($rulePattern, '<')) {
            return version_compare($clientVersion, substr($rulePattern, 1), '<');
        }

        return $clientVersion === $rulePattern;
    }
}
```

---

## 9. Renderers

### 9.1 BladeRenderer

```php
<?php

namespace Yannelli\PromptManager\Renderers;

use Yannelli\PromptManager\Contracts\PromptRendererInterface;
use Illuminate\View\Factory as ViewFactory;

class BladeRenderer implements PromptRendererInterface
{
    public function __construct(
        protected ViewFactory $viewFactory
    ) {}

    public function render(string $template, array $variables = []): string
    {
        // Render inline Blade template
        return $this->viewFactory
            ->make('prompt-manager::inline', ['__template' => $template] + $variables)
            ->render();
    }

    public function supports(string $template): bool
    {
        return str_contains($template, '{{') ||
               str_contains($template, '@') ||
               str_contains($template, '{!!');
    }
}
```

### 9.2 SimpleRenderer (Variable Substitution)

```php
<?php

namespace Yannelli\PromptManager\Renderers;

use Yannelli\PromptManager\Contracts\PromptRendererInterface;

class SimpleRenderer implements PromptRendererInterface
{
    protected string $openDelimiter = '{{';
    protected string $closeDelimiter = '}}';

    public function render(string $template, array $variables = []): string
    {
        $result = $template;

        foreach ($variables as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $result = str_replace(
                    [$this->openDelimiter . ' ' . $key . ' ' . $this->closeDelimiter,
                     $this->openDelimiter . $key . $this->closeDelimiter],
                    (string) $value,
                    $result
                );
            }
        }

        return $result;
    }

    public function supports(string $template): bool
    {
        return true; // Always supports as fallback
    }
}
```

---

## 10. Main PromptManager Service

```php
<?php

namespace Yannelli\PromptManager;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Pipelines\PromptPipeline;
use Illuminate\Pipeline\Pipeline;

class PromptManager
{
    public function __construct(
        protected Pipeline $pipeline
    ) {}

    /**
     * Get a template by slug
     */
    public function template(string $slug): ?PromptTemplate
    {
        return PromptTemplate::where('slug', $slug)->active()->first();
    }

    /**
     * Render a single template
     */
    public function render(string|PromptTemplate $template, array|PromptContext $context = []): RenderResult
    {
        if (is_string($template)) {
            $template = $this->template($template);
        }

        if (!$template) {
            throw new \RuntimeException('Template not found');
        }

        $context = is_array($context) ? PromptContext::make($context) : $context;

        return $template->typeHandler->render($template, $context);
    }

    /**
     * Create a pipeline for chaining templates
     */
    public function chain(): PromptPipeline
    {
        return new PromptPipeline($this->pipeline);
    }

    /**
     * Create a new template
     */
    public function create(array $data): PromptTemplate
    {
        $template = PromptTemplate::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? config('prompt-manager.default_type_key', 'user'),
            'metadata' => $data['metadata'] ?? null,
        ]);

        if (isset($data['content'])) {
            $template->createVersion($data['content'], $data['version_options'] ?? []);
        }

        return $template;
    }

    /**
     * Register a custom type
     */
    public function registerType(string $key, string $typeClass): void
    {
        config()->set("prompt-manager.types.{$key}", $typeClass);
    }
}
```

---

## 11. Configuration

```php
<?php

// config/prompt-manager.php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Prompt Type
    |--------------------------------------------------------------------------
    |
    | The default type to use when creating templates without specifying a type.
    |
    */
    'default_type_key' => 'user',
    'default_type' => \Yannelli\PromptManager\Types\UserPromptType::class,

    /*
    |--------------------------------------------------------------------------
    | Registered Prompt Types
    |--------------------------------------------------------------------------
    |
    | Map of type keys to their handler classes. Users can add custom types here.
    |
    */
    'types' => [
        'system' => \Yannelli\PromptManager\Types\SystemPromptType::class,
        'user' => \Yannelli\PromptManager\Types\UserPromptType::class,
        'assistant' => \Yannelli\PromptManager\Types\AssistantPromptType::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Renderer
    |--------------------------------------------------------------------------
    |
    | The renderer to use for template variable substitution.
    | Options: 'simple', 'blade'
    |
    */
    'renderer' => 'simple',

    'renderers' => [
        'simple' => \Yannelli\PromptManager\Renderers\SimpleRenderer::class,
        'blade' => \Yannelli\PromptManager\Renderers\BladeRenderer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Settings
    |--------------------------------------------------------------------------
    */
    'versioning' => [
        'auto_publish' => false,        // Auto-publish new versions
        'keep_versions' => null,        // Null = keep all, or specify number
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'templates' => 'prompt_templates',
        'versions' => 'prompt_template_versions',
        'components' => 'prompt_components',
        'executions' => 'prompt_executions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Tracking
    |--------------------------------------------------------------------------
    */
    'track_executions' => false,

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'prompt_manager',
    ],
];
```

---

## 12. Service Provider

```php
<?php

namespace Yannelli\PromptManager;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Yannelli\PromptManager\Contracts\PromptRendererInterface;
use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Versioning\VersionManager;

class PromptManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('prompt-manager')
            ->hasConfigFile()
            ->hasMigrations([
                'create_prompt_templates_table',
                'create_prompt_template_versions_table',
                'create_prompt_components_table',
                'create_prompt_executions_table',
            ])
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        // Bind the main service
        $this->app->singleton(PromptManager::class, function ($app) {
            return new PromptManager($app->make(\Illuminate\Pipeline\Pipeline::class));
        });

        // Bind the renderer based on config
        $this->app->bind(PromptRendererInterface::class, function ($app) {
            $renderer = config('prompt-manager.renderer', 'simple');
            return $app->make(config("prompt-manager.renderers.{$renderer}"));
        });

        // Bind version resolver
        $this->app->singleton(VersionResolverInterface::class, VersionManager::class);

        // Register types
        foreach (config('prompt-manager.types', []) as $key => $typeClass) {
            $this->app->bind("prompt-manager.type.{$key}", $typeClass);
        }
    }

    public function packageBooted(): void
    {
        // Register facade
        $this->app->alias(PromptManager::class, 'prompt-manager');
    }
}
```

---

## 13. Facade

```php
<?php

namespace Yannelli\PromptManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Yannelli\PromptManager\Models\PromptTemplate|null template(string $slug)
 * @method static \Yannelli\PromptManager\DTOs\RenderResult render(string|\Yannelli\PromptManager\Models\PromptTemplate $template, array|\Yannelli\PromptManager\DTOs\PromptContext $context = [])
 * @method static \Yannelli\PromptManager\Pipelines\PromptPipeline chain()
 * @method static \Yannelli\PromptManager\Models\PromptTemplate create(array $data)
 * @method static void registerType(string $key, string $typeClass)
 *
 * @see \Yannelli\PromptManager\PromptManager
 */
class PromptManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Yannelli\PromptManager\PromptManager::class;
    }
}
```

---

## 14. Usage Examples

### Basic Usage

```php
use Yannelli\PromptManager\Facades\PromptManager;

// Simple render
$result = PromptManager::render('welcome-message', [
    'variables' => ['user_name' => 'John'],
]);

echo $result->content;
// Output: "Hello, John! Welcome to our platform."
```

### With Component Toggles

```php
$result = PromptManager::render('system-prompt', [
    'variables' => ['task' => 'code review'],
    'enabled_components' => ['code_guidelines', 'security_rules'],
    'disabled_components' => ['verbose_mode'],
]);
```

### Chaining Templates

```php
$messages = PromptManager::chain()
    ->template('system-prompt', ['task' => 'translate'])
    ->template('user-input')
    ->template('format-instructions')
    ->toMessages(PromptContext::make([
        'variables' => [
            'source_language' => 'English',
            'target_language' => 'Spanish',
            'user_input' => 'Hello, how are you?',
        ],
    ]));

// Result:
// [
//     ['role' => 'system', 'content' => 'You are a translator...'],
//     ['role' => 'user', 'content' => 'Translate: Hello, how are you?'],
//     ['role' => 'user', 'content' => 'Format your response as...'],
// ]
```

### Version Mapping

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
// Uses version 2 because client is 1.5.0
```

### Custom Type Registration

```php
// In a service provider or boot method
PromptManager::registerType('tool', \App\PromptTypes\ToolPromptType::class);

// Create template with custom type
PromptManager::create([
    'slug' => 'search-tool',
    'name' => 'Search Tool Prompt',
    'type' => 'tool',
    'content' => 'Execute search for: {{ query }}',
]);
```

### Creating a Custom Type

```php
<?php

namespace App\PromptTypes;

use Yannelli\PromptManager\Types\BasePromptType;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;

class ToolPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'tool';
    }

    public function prepareContext(PromptContext $context): PromptContext
    {
        // Tool prompts always inject the tool name
        return $context->withVariables([
            'tool_version' => config('app.tool_version'),
        ]);
    }

    public function postProcess(RenderResult $result): RenderResult
    {
        // Wrap tool output in special markers
        return RenderResult::make(
            content: "<tool_call>{$result->content}</tool_call>",
            role: $this->getRole(),
            options: $result->toArray()
        );
    }
}
```

---

## 15. Summary

This design provides:

1. **Flexible Template Storage** - Templates with metadata, multiple versions, and optional components
2. **Version Management** - Support for specific versions, latest version, and custom mapping logic
3. **Component System** - Optional template parts that can be toggled based on user preferences or conditions
4. **Type System** - Extensible prompt types (system, user, assistant, custom) with invokable handlers
5. **Pipeline/Chaining** - Ability to chain multiple templates together, passing results through
6. **Multiple Renderers** - Simple variable substitution or full Blade support
7. **Clean API** - Facade, DTOs, and fluent interfaces for ease of use

The architecture follows Laravel 12 best practices:
- Uses Spatie package skeleton conventions
- Implements proper service container bindings
- Uses Pipeline pattern for extensible processing
- Follows Action pattern for business logic
- Uses DTOs for type-safe data transfer
- Publishes migrations for user customization
