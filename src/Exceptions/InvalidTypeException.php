<?php

namespace Yannelli\PromptManager\Exceptions;

class InvalidTypeException extends PromptManagerException
{
    public static function notFound(string $type): self
    {
        return new self("Prompt type '{$type}' is not registered.");
    }

    public static function invalidClass(string $type, string $class): self
    {
        return new self(
            "Prompt type '{$type}' class '{$class}' does not implement PromptTypeInterface."
        );
    }
}
