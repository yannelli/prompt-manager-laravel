<?php

namespace Yannelli\PromptManager\Exceptions;

use Throwable;

class RenderingException extends PromptManagerException
{
    public static function failed(string $templateSlug, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "Failed to render template '{$templateSlug}': {$reason}",
            0,
            $previous
        );
    }

    public static function invalidContext(string $templateSlug, array $errors): self
    {
        $errorList = implode(', ', $errors);
        return new self(
            "Invalid context for template '{$templateSlug}': {$errorList}"
        );
    }
}
