<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Contracts;

use PromptManager\PromptTemplates\DTOs\VersionMappingResult;
use PromptManager\PromptTemplates\Models\PromptTemplateVersion;

interface VersionMapperInterface
{
    /**
     * Map content from one version to another.
     */
    public function map(PromptTemplateVersion $fromVersion, PromptTemplateVersion $toVersion, array $content): VersionMappingResult;

    /**
     * Map variables from one version to another.
     */
    public function mapVariables(PromptTemplateVersion $fromVersion, PromptTemplateVersion $toVersion, array $variables): array;

    /**
     * Check if this mapper can handle the version transition.
     */
    public function supports(PromptTemplateVersion $fromVersion, PromptTemplateVersion $toVersion): bool;

    /**
     * Get the source version this mapper handles.
     */
    public function getSourceVersion(): int;

    /**
     * Get the target version this mapper produces.
     */
    public function getTargetVersion(): int;
}
