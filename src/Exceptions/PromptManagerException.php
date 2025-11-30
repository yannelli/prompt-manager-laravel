<?php

namespace Yannelli\PromptManager\Exceptions;

use Exception;

class PromptManagerException extends Exception
{
    public static function generic(string $message): self
    {
        return new self($message);
    }
}
