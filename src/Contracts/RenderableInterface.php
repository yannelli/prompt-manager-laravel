<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Contracts;

use PromptManager\PromptTemplates\DTOs\RenderedPrompt;

interface RenderableInterface
{
    /**
     * Render the object with the given variables.
     */
    public function render(array $variables = [], array $options = []): RenderedPrompt;

    /**
     * Get the variables expected by this renderable.
     */
    public function getExpectedVariables(): array;

    /**
     * Check if the provided variables satisfy requirements.
     */
    public function validateVariables(array $variables): bool;
}
