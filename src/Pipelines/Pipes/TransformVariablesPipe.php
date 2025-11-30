<?php

namespace Yannelli\PromptManager\Pipelines\Pipes;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Contracts\PipeInterface;

class TransformVariablesPipe implements PipeInterface
{
    /** @var array<string, Closure> */
    protected array $transformers;

    /**
     * @param array<string, Closure> $transformers Map of variable names to transformer functions
     */
    public function __construct(array $transformers = [])
    {
        $this->transformers = $transformers;
    }

    public function handle(PromptContext $context, Closure $next): PromptContext
    {
        $variables = $context->variables;

        foreach ($this->transformers as $key => $transformer) {
            // Support for wildcard key "*" to transform all variables
            if ($key === '*') {
                foreach ($variables as $varKey => $value) {
                    $variables[$varKey] = $transformer($value, $varKey, $context);
                }
                continue;
            }

            // Support for pattern matching (e.g., "user.*")
            if (str_contains($key, '*')) {
                $pattern = '/^' . str_replace('*', '.*', preg_quote($key, '/')) . '$/';
                foreach ($variables as $varKey => $value) {
                    if (preg_match($pattern, $varKey)) {
                        $variables[$varKey] = $transformer($value, $varKey, $context);
                    }
                }
                continue;
            }

            // Exact key match
            if (array_key_exists($key, $variables)) {
                $variables[$key] = $transformer($variables[$key], $key, $context);
            }
        }

        return $next($context->withVariables($variables));
    }

    public function addTransformer(string $key, Closure $transformer): self
    {
        $this->transformers[$key] = $transformer;
        return $this;
    }

    public static function uppercase(array $keys): self
    {
        $transformers = [];
        foreach ($keys as $key) {
            $transformers[$key] = fn($value) => is_string($value) ? strtoupper($value) : $value;
        }
        return new self($transformers);
    }

    public static function lowercase(array $keys): self
    {
        $transformers = [];
        foreach ($keys as $key) {
            $transformers[$key] = fn($value) => is_string($value) ? strtolower($value) : $value;
        }
        return new self($transformers);
    }

    public static function jsonEncode(array $keys): self
    {
        $transformers = [];
        foreach ($keys as $key) {
            $transformers[$key] = fn($value) => is_array($value) || is_object($value) ? json_encode($value) : $value;
        }
        return new self($transformers);
    }
}
