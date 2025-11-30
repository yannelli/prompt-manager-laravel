<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Renderers;

use Illuminate\Support\Facades\Cache;
use PromptManager\PromptTemplates\Contracts\RenderableInterface;
use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate;
use PromptManager\PromptTemplates\Models\PromptTemplateVersion;

class PromptRenderer
{
    /**
     * Render a template or version.
     */
    public function render(
        RenderableInterface|PromptTemplate|PromptTemplateVersion $renderable,
        array $variables = [],
        array $options = []
    ): RenderedPrompt {
        // Check cache if enabled
        if ($this->shouldCache($options)) {
            $cacheKey = $this->getCacheKey($renderable, $variables, $options);
            $cacheTtl = config('prompt-templates.cache.ttl', 3600);

            return Cache::remember($cacheKey, $cacheTtl, function () use ($renderable, $variables, $options) {
                return $this->performRender($renderable, $variables, $options);
            });
        }

        return $this->performRender($renderable, $variables, $options);
    }

    /**
     * Perform the actual rendering.
     */
    protected function performRender(
        RenderableInterface|PromptTemplate|PromptTemplateVersion $renderable,
        array $variables,
        array $options
    ): RenderedPrompt {
        // If it's a PromptTemplate with a type, use the type handler
        if ($renderable instanceof PromptTemplate && $renderable->type) {
            return $renderable->type->getHandler()($renderable, $variables, $options);
        }

        // Default rendering
        return $this->defaultRender($renderable, $variables, $options);
    }

    /**
     * Default render implementation.
     */
    protected function defaultRender(
        RenderableInterface|PromptTemplate|PromptTemplateVersion $renderable,
        array $variables,
        array $options
    ): RenderedPrompt {
        $expected = $renderable->getExpectedVariables();
        $usedVariables = [];

        $systemPrompt = $this->substituteVariables(
            $renderable->system_prompt,
            $variables,
            $expected,
            $usedVariables
        );

        $userPrompt = $this->substituteVariables(
            $renderable->user_prompt,
            $variables,
            $expected,
            $usedVariables
        );

        $assistantPrompt = $this->substituteVariables(
            $renderable->assistant_prompt,
            $variables,
            $expected,
            $usedVariables
        );

        $content = $this->substituteVariables(
            $renderable->content,
            $variables,
            $expected,
            $usedVariables
        );

        // Apply components if it's a template
        if ($renderable instanceof PromptTemplate && ($options['with_components'] ?? true)) {
            $userId = $options['user_id'] ?? null;
            [$systemPrompt, $userPrompt, $assistantPrompt, $content] = $this->applyComponents(
                $renderable,
                $systemPrompt,
                $userPrompt,
                $assistantPrompt,
                $content,
                $variables,
                $userId
            );
        }

        $templateId = null;
        $version = null;

        if ($renderable instanceof PromptTemplate) {
            $templateId = $renderable->uuid;
            $version = $renderable->current_version;
        } elseif ($renderable instanceof PromptTemplateVersion) {
            $templateId = $renderable->template->uuid ?? null;
            $version = $renderable->version;
        }

        return new RenderedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            assistantPrompt: $assistantPrompt,
            content: $content,
            metadata: $renderable->metadata ?? [],
            templateId: $templateId,
            version: $version,
            usedVariables: $usedVariables,
        );
    }

    /**
     * Substitute variables in content.
     */
    protected function substituteVariables(
        ?string $content,
        array $variables,
        array $expected,
        array &$usedVariables
    ): ?string {
        if ($content === null) {
            return null;
        }

        // Apply default values from expected variables
        foreach ($expected as $name => $config) {
            if (! array_key_exists($name, $variables) && array_key_exists('default', $config)) {
                $variables[$name] = $config['default'];
            }
        }

        $delimiters = config('prompt-templates.variable_delimiters', [
            'start' => '{{',
            'end' => '}}',
        ]);

        $pattern = '/'.preg_quote($delimiters['start'], '/')
            .'\\s*([\\w.]+)\\s*'
            .preg_quote($delimiters['end'], '/').'/';

        return preg_replace_callback($pattern, function ($matches) use ($variables, &$usedVariables) {
            $key = $matches[1];

            // Support dot notation
            if (str_contains($key, '.')) {
                $value = data_get($variables, $key);
            } else {
                $value = $variables[$key] ?? null;
            }

            if ($value !== null) {
                $usedVariables[$key] = $value;
            }

            if ($value === null) {
                return $matches[0]; // Keep placeholder if no value
            }

            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            }

            return (string) $value;
        }, $content);
    }

    /**
     * Apply template components.
     */
    protected function applyComponents(
        PromptTemplate $template,
        ?string $systemPrompt,
        ?string $userPrompt,
        ?string $assistantPrompt,
        ?string $content,
        array $variables,
        ?int $userId
    ): array {
        $components = $template->enabledComponentsForUser($userId)->get();

        foreach ($components as $component) {
            // Check conditions
            if (! $this->evaluateConditions($component->pivot->conditions ?? [], $variables)) {
                continue;
            }

            // Apply variable overrides
            $componentVariables = $variables;
            if ($component->pivot->variable_overrides) {
                $componentVariables = array_merge($variables, $component->pivot->variable_overrides);
            }

            $renderedContent = $component->render($componentVariables);
            $target = $component->pivot->target ?? 'user_prompt';
            $position = $component->pivot->position ?? $component->position ?? 'append';

            match ($target) {
                'system_prompt' => $systemPrompt = $this->applyComponentContent($systemPrompt, $renderedContent, $position),
                'user_prompt' => $userPrompt = $this->applyComponentContent($userPrompt, $renderedContent, $position),
                'assistant_prompt' => $assistantPrompt = $this->applyComponentContent($assistantPrompt, $renderedContent, $position),
                'content' => $content = $this->applyComponentContent($content, $renderedContent, $position),
                default => $userPrompt = $this->applyComponentContent($userPrompt, $renderedContent, $position),
            };
        }

        return [$systemPrompt, $userPrompt, $assistantPrompt, $content];
    }

    /**
     * Apply component content based on position.
     */
    protected function applyComponentContent(?string $current, string $componentContent, string $position): string
    {
        if ($current === null) {
            return $componentContent;
        }

        return match ($position) {
            'prepend' => $componentContent."\n\n".$current,
            'append' => $current."\n\n".$componentContent,
            'replace' => $componentContent,
            default => $current."\n\n".$componentContent,
        };
    }

    /**
     * Evaluate component conditions.
     */
    protected function evaluateConditions(array $conditions, array $variables): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '==';
            $value = $condition['value'] ?? null;

            if ($field === null) {
                continue;
            }

            $contextValue = $variables[$field] ?? data_get($variables, $field);

            $passed = match ($operator) {
                '==' => $contextValue == $value,
                '===' => $contextValue === $value,
                '!=' => $contextValue != $value,
                '!==' => $contextValue !== $value,
                '>' => $contextValue > $value,
                '>=' => $contextValue >= $value,
                '<' => $contextValue < $value,
                '<=' => $contextValue <= $value,
                'in' => is_array($value) && in_array($contextValue, $value),
                'contains' => is_string($contextValue) && str_contains($contextValue, $value),
                'empty' => empty($contextValue),
                'not_empty' => ! empty($contextValue),
                default => true,
            };

            if (! $passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear the render cache for a template.
     */
    public function clearCache(PromptTemplate|PromptTemplateVersion $renderable): void
    {
        $prefix = config('prompt-templates.cache.prefix', 'prompt_templates');

        if ($renderable instanceof PromptTemplate) {
            Cache::forget("{$prefix}:template:{$renderable->uuid}:*");
        } elseif ($renderable instanceof PromptTemplateVersion) {
            Cache::forget("{$prefix}:version:{$renderable->uuid}:*");
        }
    }

    /**
     * Check if caching should be used.
     */
    protected function shouldCache(array $options): bool
    {
        if (isset($options['cache'])) {
            return (bool) $options['cache'];
        }

        return config('prompt-templates.cache.enabled', true);
    }

    /**
     * Generate cache key.
     */
    protected function getCacheKey(
        RenderableInterface|PromptTemplate|PromptTemplateVersion $renderable,
        array $variables,
        array $options
    ): string {
        $prefix = config('prompt-templates.cache.prefix', 'prompt_templates');
        $type = $renderable instanceof PromptTemplate ? 'template' : 'version';
        $id = $renderable->uuid ?? $renderable->id;
        $varsHash = md5(json_encode($variables));
        $optsHash = md5(json_encode($options));

        return "{$prefix}:{$type}:{$id}:{$varsHash}:{$optsHash}";
    }
}
