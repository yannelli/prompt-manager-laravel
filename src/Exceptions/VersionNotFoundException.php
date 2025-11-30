<?php

namespace Yannelli\PromptManager\Exceptions;

class VersionNotFoundException extends PromptManagerException
{
    public static function forTemplate(string $templateSlug, int $versionNumber): self
    {
        return new self(
            "Version {$versionNumber} not found for template '{$templateSlug}'."
        );
    }

    public static function noVersions(string $templateSlug): self
    {
        return new self(
            "No versions found for template '{$templateSlug}'."
        );
    }
}
