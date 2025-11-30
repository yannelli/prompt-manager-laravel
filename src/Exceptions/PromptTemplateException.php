<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Exceptions;

use Exception;

class PromptTemplateException extends Exception
{
    public static function templateNotFound(string $identifier): self
    {
        return new self("Prompt template '{$identifier}' not found.");
    }

    public static function versionNotFound(int $version, string $templateId): self
    {
        return new self("Version {$version} not found for template '{$templateId}'.");
    }

    public static function templateLocked(string $templateId): self
    {
        return new self("Prompt template '{$templateId}' is locked and cannot be modified.");
    }

    public static function invalidVariables(array $missing): self
    {
        $vars = implode(', ', $missing);

        return new self("Missing required variables: {$vars}");
    }

    public static function handlerNotFound(string $handlerClass): self
    {
        return new self("Type handler '{$handlerClass}' not found.");
    }

    public static function invalidHandler(string $handlerClass): self
    {
        return new self("Type handler '{$handlerClass}' must implement TypeHandlerInterface.");
    }

    public static function mapperNotFound(string $mapperClass): self
    {
        return new self("Version mapper '{$mapperClass}' not found.");
    }

    public static function mappingFailed(int $fromVersion, int $toVersion, string $reason): self
    {
        return new self("Failed to map from version {$fromVersion} to {$toVersion}: {$reason}");
    }
}
