<?php

namespace Yannelli\PromptManager\Exceptions;

class TemplateNotFoundException extends PromptManagerException
{
    public static function withSlug(string $slug): self
    {
        return new self("Prompt template with slug '{$slug}' not found.");
    }

    public static function withId(int $id): self
    {
        return new self("Prompt template with ID '{$id}' not found.");
    }
}
