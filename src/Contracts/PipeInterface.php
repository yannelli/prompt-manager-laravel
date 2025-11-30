<?php

namespace Yannelli\PromptManager\Contracts;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;

interface PipeInterface
{
    /**
     * Handle the context and pass to the next pipe
     */
    public function handle(PromptContext $context, Closure $next): PromptContext;
}
