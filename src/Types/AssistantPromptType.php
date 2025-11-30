<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;

class AssistantPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'assistant';
    }

    public function prepareContext(PromptContext $context): PromptContext
    {
        $context = parent::prepareContext($context);

        // Assistant prompts might have specific formatting preferences
        return $context;
    }

    public function postProcess(RenderResult $result): RenderResult
    {
        // Could add assistant-specific post-processing here
        // e.g., ensuring proper formatting, adding metadata
        return $result;
    }
}
