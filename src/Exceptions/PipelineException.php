<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Exceptions;

use Exception;

class PipelineException extends Exception
{
    public static function maxDepthExceeded(int $depth): self
    {
        return new self("Maximum pipeline depth ({$depth}) exceeded.");
    }

    public static function stepFailed(string $stepName, string $reason): self
    {
        return new self("Pipeline step '{$stepName}' failed: {$reason}");
    }

    public static function invalidInput(string $reason): self
    {
        return new self("Pipeline input validation failed: {$reason}");
    }

    public static function templateNotFound(string $identifier): self
    {
        return new self("Template '{$identifier}' not found in pipeline.");
    }
}
