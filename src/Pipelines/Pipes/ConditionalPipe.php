<?php

namespace Yannelli\PromptManager\Pipelines\Pipes;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Contracts\PipeInterface;

class ConditionalPipe implements PipeInterface
{
    protected Closure $condition;
    protected PipeInterface|Closure $truePipe;
    protected PipeInterface|Closure|null $falsePipe;

    public function __construct(
        Closure $condition,
        PipeInterface|Closure $truePipe,
        PipeInterface|Closure|null $falsePipe = null
    ) {
        $this->condition = $condition;
        $this->truePipe = $truePipe;
        $this->falsePipe = $falsePipe;
    }

    public function handle(PromptContext $context, Closure $next): PromptContext
    {
        $conditionResult = ($this->condition)($context);

        if ($conditionResult) {
            $context = $this->executePipe($this->truePipe, $context);
        } elseif ($this->falsePipe !== null) {
            $context = $this->executePipe($this->falsePipe, $context);
        }

        return $next($context);
    }

    protected function executePipe(PipeInterface|Closure $pipe, PromptContext $context): PromptContext
    {
        if ($pipe instanceof PipeInterface) {
            return $pipe->handle($context, fn($ctx) => $ctx);
        }

        return $pipe($context);
    }

    public static function when(string $variable, mixed $value = true): Closure
    {
        return function (PromptContext $context) use ($variable, $value) {
            $contextValue = $context->variables[$variable] ?? null;
            return $contextValue === $value;
        };
    }

    public static function whenHas(string $variable): Closure
    {
        return function (PromptContext $context) use ($variable) {
            return array_key_exists($variable, $context->variables)
                && $context->variables[$variable] !== null
                && $context->variables[$variable] !== '';
        };
    }

    public static function whenMissing(string $variable): Closure
    {
        return function (PromptContext $context) use ($variable) {
            return !array_key_exists($variable, $context->variables)
                || $context->variables[$variable] === null;
        };
    }
}
