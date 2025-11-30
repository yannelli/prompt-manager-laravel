<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Contracts;

use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate;

interface TypeHandlerInterface
{
    /**
     * Handle and render the prompt template.
     */
    public function __invoke(PromptTemplate $template, array $variables = [], array $options = []): RenderedPrompt;

    /**
     * Validate the template structure for this type.
     */
    public function validate(PromptTemplate $template): bool;

    /**
     * Get the schema for variables expected by this type.
     */
    public function getVariableSchema(): array;

    /**
     * Get the configuration options for this handler.
     */
    public function getConfig(): array;

    /**
     * Set configuration options.
     */
    public function setConfig(array $config): static;
}
