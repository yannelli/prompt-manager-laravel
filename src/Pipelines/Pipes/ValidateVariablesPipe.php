<?php

namespace Yannelli\PromptManager\Pipelines\Pipes;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Contracts\PipeInterface;
use Yannelli\PromptManager\Exceptions\RenderingException;

class ValidateVariablesPipe implements PipeInterface
{
    protected array $requiredVariables;
    protected array $variableTypes;
    protected bool $strict;

    public function __construct(
        array $requiredVariables = [],
        array $variableTypes = [],
        bool $strict = false
    ) {
        $this->requiredVariables = $requiredVariables;
        $this->variableTypes = $variableTypes;
        $this->strict = $strict;
    }

    public function handle(PromptContext $context, Closure $next): PromptContext
    {
        $errors = [];

        // Check required variables
        foreach ($this->requiredVariables as $key) {
            if (!array_key_exists($key, $context->variables)) {
                $errors[] = "Missing required variable: {$key}";
            } elseif ($context->variables[$key] === null || $context->variables[$key] === '') {
                $errors[] = "Required variable is empty: {$key}";
            }
        }

        // Check variable types
        foreach ($this->variableTypes as $key => $type) {
            if (!array_key_exists($key, $context->variables)) {
                continue;
            }

            $value = $context->variables[$key];

            if (!$this->checkType($value, $type)) {
                $actualType = gettype($value);
                $errors[] = "Variable '{$key}' should be {$type}, got {$actualType}";
            }
        }

        if (!empty($errors)) {
            if ($this->strict) {
                throw RenderingException::invalidContext('pipeline', $errors);
            }

            // In non-strict mode, add errors to metadata for debugging
            $context = $context->withMetadata(['validation_errors' => $errors]);
        }

        return $next($context);
    }

    protected function checkType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value),
            'numeric' => is_numeric($value),
            'scalar' => is_scalar($value),
            'callable' => is_callable($value),
            default => $value instanceof $type,
        };
    }
}
