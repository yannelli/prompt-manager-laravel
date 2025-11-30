<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;

class ToolPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'tool';
    }

    public function validateContext(PromptContext $context): array
    {
        $errors = parent::validateContext($context);

        // Tool prompts typically require a tool_name
        if (empty($context->variables['tool_name'] ?? null)) {
            $errors[] = 'Tool prompts require a tool_name variable';
        }

        return $errors;
    }

    public function postProcess(RenderResult $result): RenderResult
    {
        // Optionally wrap tool output in markers
        $metadata = $result->metadata;
        $metadata['is_tool_call'] = true;

        return $result->withMetadata($metadata);
    }
}
