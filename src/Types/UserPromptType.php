<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\DTOs\PromptContext;

class UserPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'user';
    }

    public function validateContext(PromptContext $context): array
    {
        $errors = [];

        // User prompts can optionally require user_input when not chaining
        // This is a soft validation - can be overridden by template configuration

        return $errors;
    }
}
