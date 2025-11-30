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
