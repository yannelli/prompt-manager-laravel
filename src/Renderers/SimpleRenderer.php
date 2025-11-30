<?php

namespace Yannelli\PromptManager\Renderers;

use Yannelli\PromptManager\Contracts\PromptRendererInterface;

class SimpleRenderer implements PromptRendererInterface
{
    protected string $openDelimiter;
    protected string $closeDelimiter;

    public function __construct(
        ?string $openDelimiter = null,
        ?string $closeDelimiter = null
    ) {
        $this->openDelimiter = $openDelimiter ?? '{{';
        $this->closeDelimiter = $closeDelimiter ?? '}}';
    }

    public function render(string $template, array $variables = []): string
    {
        $result = $template;

        foreach ($variables as $key => $value) {
            $result = $this->replaceVariable($result, $key, $value);
        }

        return $result;
    }

    protected function replaceVariable(string $content, string $key, mixed $value): string
    {
        // Handle nested arrays/objects using dot notation
        if (is_array($value) || is_object($value)) {
            $flattened = $this->flattenValue($value, $key);
            foreach ($flattened as $flatKey => $flatValue) {
                $content = $this->replaceSimpleVariable($content, $flatKey, $flatValue);
            }
            // Also handle JSON encoding for the full array
            $content = $this->replaceSimpleVariable($content, $key, json_encode($value));
        } else {
            $content = $this->replaceSimpleVariable($content, $key, $value);
        }

        return $content;
    }

    protected function replaceSimpleVariable(string $content, string $key, mixed $value): string
    {
        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return $content;
        }

        $stringValue = (string) $value;

        // Replace with various spacing formats
        $patterns = [
            $this->openDelimiter . ' ' . $key . ' ' . $this->closeDelimiter,
            $this->openDelimiter . $key . $this->closeDelimiter,
            $this->openDelimiter . '  ' . $key . '  ' . $this->closeDelimiter,
        ];

        return str_replace($patterns, $stringValue, $content);
    }

    protected function flattenValue(array|object $value, string $prefix): array
    {
        $result = [];
        $array = is_object($value) ? (array) $value : $value;

        foreach ($array as $key => $val) {
            $newKey = $prefix . '.' . $key;

            if (is_array($val) || is_object($val)) {
                $result = array_merge($result, $this->flattenValue($val, $newKey));
            } else {
                $result[$newKey] = $val;
            }
        }

        return $result;
    }

    public function supports(string $template): bool
    {
        // Always supports as the fallback renderer
        return true;
    }

    public function setDelimiters(string $open, string $close): self
    {
        $this->openDelimiter = $open;
        $this->closeDelimiter = $close;
        return $this;
    }
}
