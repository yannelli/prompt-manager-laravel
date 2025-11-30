<?php

namespace Yannelli\PromptManager\Contracts;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\DTOs\PromptContext;

interface VersionResolverInterface
{
    /**
     * Resolve the appropriate version for the given context
     */
    public function resolve(PromptTemplate $template, PromptContext $context): PromptTemplateVersion;
}
