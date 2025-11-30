<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Handlers;

use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate;

class DefaultTypeHandler extends AbstractTypeHandler
{
    /**
     * Handle and render the prompt template.
     */
    public function __invoke(PromptTemplate $template, array $variables = [], array $options = []): RenderedPrompt
    {
        $expected = $template->getExpectedVariables();
        $usedVariables = [];

        $systemPrompt = null;
        $userPrompt = null;
        $assistantPrompt = null;
        $content = null;

        if ($template->system_prompt) {
            $systemPrompt = $this->substituteVariables($template->system_prompt, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->system_prompt, $systemPrompt, $variables));
        }

        if ($template->user_prompt) {
            $userPrompt = $this->substituteVariables($template->user_prompt, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->user_prompt, $userPrompt, $variables));
        }

        if ($template->assistant_prompt) {
            $assistantPrompt = $this->substituteVariables($template->assistant_prompt, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->assistant_prompt, $assistantPrompt, $variables));
        }

        if ($template->content) {
            $content = $this->substituteVariables($template->content, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->content, $content, $variables));
        }

        // Apply components if enabled
        if ($options['with_components'] ?? true) {
            $userId = $options['user_id'] ?? null;
            [$systemPrompt, $userPrompt, $assistantPrompt, $content] = $this->applyComponents(
                $template,
                $systemPrompt,
                $userPrompt,
                $assistantPrompt,
                $content,
                $variables,
                $userId
            );
        }

        return new RenderedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            assistantPrompt: $assistantPrompt,
            content: $content,
            metadata: $template->metadata ?? [],
            templateId: $template->uuid,
            version: $template->current_version,
            usedVariables: $usedVariables,
        );
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
            $renderedContent = $component->render($variables);
            $target = $component->pivot->target ?? 'user_prompt';
            $position = $component->pivot->position ?? $component->position ?? 'append';

            $currentValue = match ($target) {
                'system_prompt' => &$systemPrompt,
                'user_prompt' => &$userPrompt,
                'assistant_prompt' => &$assistantPrompt,
                'content' => &$content,
                default => &$userPrompt,
            };

            $currentValue = $this->applyComponentContent($currentValue, $renderedContent, $position);
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
}
