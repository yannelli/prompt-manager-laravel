<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\DTOs\PromptContext;

class SystemPromptType extends BasePromptType
{
    public function getRole(): string
    {
        return 'system';
    }

    public function prepareContext(PromptContext $context): PromptContext
    {
        $context = parent::prepareContext($context);

        // System prompts automatically get date/time context
        return $context->withVariables([
            'current_date' => now()->toDateString(),
            'current_time' => now()->toTimeString(),
            'current_datetime' => now()->toDateTimeString(),
            'timezone' => config('app.timezone', 'UTC'),
        ]);
    }
}
