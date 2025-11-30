<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Handlers;

use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate;

class ChatTypeHandler extends AbstractTypeHandler
{
    /**
     * Handle and render the prompt template for chat-style APIs.
     */
    public function __invoke(PromptTemplate $template, array $variables = [], array $options = []): RenderedPrompt
    {
        $expected = $template->getExpectedVariables();
        $usedVariables = [];

        $systemPrompt = null;
        $userPrompt = null;
        $assistantPrompt = null;

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

        // Apply components
        if ($options['with_components'] ?? true) {
            $userId = $options['user_id'] ?? null;
            [$systemPrompt, $userPrompt, $assistantPrompt] = $this->applyComponents(
                $template,
                $systemPrompt,
                $userPrompt,
                $assistantPrompt,
                $variables,
                $userId
            );
        }

        // Build messages array
        $messages = [];

        if ($systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        // Include conversation history if provided
        if (isset($variables['_messages']) && is_array($variables['_messages'])) {
            foreach ($variables['_messages'] as $message) {
                if (isset($message['role']) && isset($message['content'])) {
                    $messages[] = $message;
                }
            }
        }

        if ($userPrompt !== null) {
            $messages[] = ['role' => 'user', 'content' => $userPrompt];
        }

        if ($assistantPrompt !== null) {
            $messages[] = ['role' => 'assistant', 'content' => $assistantPrompt];
        }

        return new RenderedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            assistantPrompt: $assistantPrompt,
            messages: $messages,
            metadata: array_merge($template->metadata ?? [], [
                'format' => 'chat',
                'message_count' => count($messages),
            ]),
            templateId: $template->uuid,
            version: $template->current_version,
            usedVariables: $usedVariables,
        );
    }

    /**
     * Validate that the template has chat-compatible structure.
     */
    public function validate(PromptTemplate $template): bool
    {
        // Chat templates should have at least a system or user prompt
        return $template->system_prompt !== null || $template->user_prompt !== null;
    }

    /**
     * Get the variable schema for chat templates.
     */
    public function getVariableSchema(): array
    {
        return [
            '_messages' => [
                'type' => 'array',
                'description' => 'Optional conversation history to include',
                'required' => false,
            ],
        ];
    }

    /**
     * Apply template components.
     */
    protected function applyComponents(
        PromptTemplate $template,
        ?string $systemPrompt,
        ?string $userPrompt,
        ?string $assistantPrompt,
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
                default => &$userPrompt,
            };

            $currentValue = $this->applyComponentContent($currentValue, $renderedContent, $position);
        }

        return [$systemPrompt, $userPrompt, $assistantPrompt];
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
