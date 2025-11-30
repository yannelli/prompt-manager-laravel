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
