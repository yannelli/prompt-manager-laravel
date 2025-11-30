<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Handlers;

use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate;

class InstructionTypeHandler extends AbstractTypeHandler
{
    protected array $config = [
        'instruction_prefix' => '### Instruction:\n',
        'input_prefix' => '### Input:\n',
        'response_prefix' => '### Response:\n',
        'include_input_section' => true,
    ];

    /**
     * Handle and render the prompt template in instruction format.
     */
    public function __invoke(PromptTemplate $template, array $variables = [], array $options = []): RenderedPrompt
    {
        $expected = $template->getExpectedVariables();
        $usedVariables = [];

        $parts = [];

        // Build instruction section
        if ($template->system_prompt) {
            $instruction = $this->substituteVariables($template->system_prompt, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->system_prompt, $instruction, $variables));
            $parts[] = $this->config['instruction_prefix'].$instruction;
        }

        // Build input section
        if ($this->config['include_input_section'] && $template->user_prompt) {
            $input = $this->substituteVariables($template->user_prompt, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->user_prompt, $input, $variables));
            $parts[] = $this->config['input_prefix'].$input;
        } elseif ($template->user_prompt) {
            $input = $this->substituteVariables($template->user_prompt, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->user_prompt, $input, $variables));
            $parts[] = $input;
        }

        // Apply components
        if ($options['with_components'] ?? true) {
            $userId = $options['user_id'] ?? null;
            $componentContent = $this->getComponentContent($template, $variables, $userId);

            if ($componentContent) {
                $parts[] = $componentContent;
            }
        }

        // Add response prefix
        $parts[] = $this->config['response_prefix'];

        // Add any pre-filled response
        if ($template->assistant_prompt) {
            $response = $this->substituteVariables($template->assistant_prompt, $variables, $expected);
            $usedVariables = array_merge($usedVariables, $this->extractUsedVariables($template->assistant_prompt, $response, $variables));
            $parts[count($parts) - 1] .= $response;
        }

        $content = implode("\n\n", array_filter($parts));

        return new RenderedPrompt(
            content: $content,
            systemPrompt: $template->system_prompt ? $this->substituteVariables($template->system_prompt, $variables, $expected) : null,
            userPrompt: $template->user_prompt ? $this->substituteVariables($template->user_prompt, $variables, $expected) : null,
            assistantPrompt: $template->assistant_prompt ? $this->substituteVariables($template->assistant_prompt, $variables, $expected) : null,
            metadata: array_merge($template->metadata ?? [], [
                'format' => 'instruction',
                'template_id' => $template->uuid,
                'version' => $template->current_version,
            ]),
            templateId: $template->uuid,
            version: $template->current_version,
            usedVariables: $usedVariables,
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
     * Validate that the template has instruction-compatible structure.
     */
    public function validate(PromptTemplate $template): bool
    {
        // Instruction templates should have at least a system prompt (instruction)
        return $template->system_prompt !== null;
    }

    /**
     * Get the variable schema for instruction templates.
     */
    public function getVariableSchema(): array
    {
        return [
            'instruction' => [
                'type' => 'string',
                'description' => 'The instruction/task to perform',
                'required' => false,
            ],
            'input' => [
                'type' => 'string',
                'description' => 'The input data to process',
                'required' => false,
            ],
        ];
    }
}
