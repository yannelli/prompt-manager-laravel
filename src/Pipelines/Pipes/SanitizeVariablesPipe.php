<?php

namespace Yannelli\PromptManager\Pipelines\Pipes;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Contracts\PipeInterface;

class SanitizeVariablesPipe implements PipeInterface
{
    protected bool $escapeHtml;
    protected bool $trimStrings;
    protected int $maxStringLength;

    public function __construct(
        bool $escapeHtml = false,
        bool $trimStrings = true,
        int $maxStringLength = 0
    ) {
        $this->escapeHtml = $escapeHtml;
        $this->trimStrings = $trimStrings;
        $this->maxStringLength = $maxStringLength;
    }

    public function handle(PromptContext $context, Closure $next): PromptContext
    {
        $sanitized = $this->sanitizeArray($context->variables);

        return $next($context->withVariables($sanitized));
    }

    protected function sanitizeArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = $this->sanitizeValue($value);
        }

        return $result;
    }

    protected function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        return $value;
    }

    protected function sanitizeString(string $value): string
    {
        if ($this->trimStrings) {
            $value = trim($value);
        }

        if ($this->escapeHtml) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($this->maxStringLength > 0 && strlen($value) > $this->maxStringLength) {
            $value = substr($value, 0, $this->maxStringLength) . '...';
        }

        return $value;
    }
}
