<?php

namespace Yannelli\PromptManager;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Cache;
use Yannelli\PromptManager\Contracts\PromptTypeInterface;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\DTOs\TemplateData;
use Yannelli\PromptManager\Exceptions\InvalidTypeException;
use Yannelli\PromptManager\Exceptions\TemplateNotFoundException;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Pipelines\PromptPipeline;

class PromptManager
{
    protected Pipeline $pipeline;

    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Get a template by slug
     */
    public function template(string $slug): ?PromptTemplate
    {
        $cacheKey = $this->getCacheKey("template.{$slug}");

        if ($this->isCacheEnabled()) {
            return Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($slug) {
                return PromptTemplate::where('slug', $slug)->active()->first();
            });
        }

        return PromptTemplate::where('slug', $slug)->active()->first();
    }

    /**
     * Get a template by slug or throw exception
     */
    public function templateOrFail(string $slug): PromptTemplate
    {
        $template = $this->template($slug);

        if (! $template) {
            throw TemplateNotFoundException::withSlug($slug);
        }

        return $template;
    }

    /**
     * Render a single template
     */
    public function render(string|PromptTemplate $template, array|PromptContext $context = []): RenderResult
    {
        if (is_string($template)) {
            $template = $this->templateOrFail($template);
        }

        $context = $this->normalizeContext($context);

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
    public function create(array|TemplateData $data): PromptTemplate
    {
        if ($data instanceof TemplateData) {
            $data = $data->toArray();
        }

        $template = PromptTemplate::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? config('prompt-manager.default_type_key', 'user'),
            'metadata' => $data['metadata'] ?? null,
        ]);

        if (isset($data['content'])) {
            $template->createVersion($data['content'], [
                'variables' => $data['variables'] ?? null,
                'change_summary' => $data['change_summary'] ?? 'Initial version',
                'created_by' => $data['created_by'] ?? null,
            ]);
        }

        // Create components if provided
        if (! empty($data['components'])) {
            foreach ($data['components'] as $component) {
                $template->addComponent($component);
            }
        }

        $this->clearTemplateCache($template->slug);

        return $template->fresh(['currentVersion', 'components']);
    }

    /**
     * Update an existing template
     */
    public function update(string|PromptTemplate $template, array $data): PromptTemplate
    {
        if (is_string($template)) {
            $template = $this->templateOrFail($template);
        }

        $template->update([
            'name' => $data['name'] ?? $template->name,
            'description' => $data['description'] ?? $template->description,
            'type' => $data['type'] ?? $template->type,
            'metadata' => $data['metadata'] ?? $template->metadata,
        ]);

        // Create new version if content is provided
        if (isset($data['content'])) {
            $template->createVersion($data['content'], [
                'variables' => $data['variables'] ?? null,
                'change_summary' => $data['change_summary'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        }

        $this->clearTemplateCache($template->slug);

        return $template->fresh(['currentVersion', 'components']);
    }

    /**
     * Delete a template
     */
    public function delete(string|PromptTemplate $template): bool
    {
        if (is_string($template)) {
            $template = $this->templateOrFail($template);
        }

        $slug = $template->slug;
        $result = $template->delete();

        $this->clearTemplateCache($slug);

        return $result;
    }

    /**
     * Register a custom type
     */
    public function registerType(string $key, string $typeClass): void
    {
        if (! is_subclass_of($typeClass, PromptTypeInterface::class)) {
            throw InvalidTypeException::invalidClass($key, $typeClass);
        }

        config()->set("prompt-manager.types.{$key}", $typeClass);
    }

    /**
     * Get all registered types
     */
    public function getTypes(): array
    {
        return config('prompt-manager.types', []);
    }

    /**
     * Check if a type is registered
     */
    public function hasType(string $key): bool
    {
        return config()->has("prompt-manager.types.{$key}");
    }

    /**
     * Get all templates
     */
    public function all(bool $includeInactive = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = PromptTemplate::with(['currentVersion', 'components']);

        if (! $includeInactive) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Find templates by type
     */
    public function byType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return PromptTemplate::ofType($type)->active()->with(['currentVersion'])->get();
    }

    /**
     * Search templates
     */
    public function search(string $query): \Illuminate\Database\Eloquent\Collection
    {
        return PromptTemplate::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('slug', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
        })->active()->get();
    }

    /**
     * Clear cache for a template
     */
    public function clearTemplateCache(string $slug): void
    {
        if ($this->isCacheEnabled()) {
            Cache::forget($this->getCacheKey("template.{$slug}"));
        }
    }

    /**
     * Clear all prompt manager cache
     */
    public function clearCache(): void
    {
        if ($this->isCacheEnabled()) {
            // This is a simple approach - for production you might want
            // to use cache tags if your cache driver supports them
            $templates = PromptTemplate::pluck('slug');
            foreach ($templates as $slug) {
                $this->clearTemplateCache($slug);
            }
        }
    }

    protected function normalizeContext(array|PromptContext $context): PromptContext
    {
        if ($context instanceof PromptContext) {
            return $context;
        }

        return PromptContext::make($context);
    }

    protected function isCacheEnabled(): bool
    {
        return config('prompt-manager.cache.enabled', true);
    }

    protected function getCacheTtl(): int
    {
        return config('prompt-manager.cache.ttl', 3600);
    }

    protected function getCacheKey(string $key): string
    {
        $prefix = config('prompt-manager.cache.prefix', 'prompt_manager');

        return "{$prefix}.{$key}";
    }
}
