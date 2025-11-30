<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Handlers;

use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate;

class CompletionTypeHandler extends AbstractTypeHandler
{
    /**
     * Handle and render the prompt template for completion-style APIs.
     */
    public function __invoke(PromptTemplate $template, array $variables = [], array $options = []): RenderedPrompt
    {
        $expected = $template->getExpectedVariables();
        $usedVariables = [];

        // For completion, we combine all parts into a single content string
        $parts = [];

        if ($template->system_prompt) {
            $rendered = $this->substituteVariables($template->system_prompt, $variables, $expected);
            $parts[] = $rendered;
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->system_prompt, $rendered, $variables));
        }

        if ($template->content) {
            $rendered = $this->substituteVariables($template->content, $variables, $expected);
            $parts[] = $rendered;
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->content, $rendered, $variables));
        }

        if ($template->user_prompt) {
            $rendered = $this->substituteVariables($template->user_prompt, $variables, $expected);
            $parts[] = $rendered;
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->user_prompt, $rendered, $variables));
        }

        // Apply components
        if ($options['with_components'] ?? true) {
            $userId = $options['user_id'] ?? null;
            $componentContent = $this->getComponentContent($template, $variables, $userId);

            if ($componentContent) {
                $parts[] = $componentContent;
            }
        }

        $separator = $this->config['separator'] ?? "\n\n";
        $content = implode($separator, array_filter($parts));

        // Add suffix if configured
        if (isset($this->config['suffix'])) {
            $content .= $this->config['suffix'];
        }

        return RenderedPrompt::fromCompletion(
            content: $content,
            metadata: array_merge($template->metadata ?? [], [
                'format' => 'completion',
                'template_id' => $template->uuid,
                'version' => $template->current_version,
                'used_variables' => $usedVariables,
            ])
        );
    }

    /**
     * Get concatenated component content.
     */
    protected function getComponentContent(PromptTemplate $template, array $variables, ?int $userId): string
    {
        $components = $template->enabledComponentsForUser($userId)->get();
        $parts = [];

        foreach ($components as $component) {
            $parts[] = $component->render($variables);
        }

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Validate that the template has completion-compatible structure.
     */
    public function validate(PromptTemplate $template): bool
    {
        // Completion templates should have content or user prompt
        return $template->content !== null || $template->user_prompt !== null;
    }
}
