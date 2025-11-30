<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Handlers;

use PromptManager\PromptTemplates\Contracts\TypeHandlerInterface;
use PromptManager\PromptTemplates\DTOs\RenderedPrompt;
use PromptManager\PromptTemplates\Models\PromptTemplate;

abstract class AbstractTypeHandler implements TypeHandlerInterface
{
    protected array $config = [];

    /**
     * Handle and render the prompt template.
     */
    abstract public function __invoke(PromptTemplate $template, array $variables = [], array $options = []): RenderedPrompt;

    /**
     * Get the schema for variables expected by this type.
     */
    public function getVariableSchema(): array
    {
        return [];
    }

    /**
     * Get the configuration options for this handler.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set configuration options.
     */
    public function setConfig(array $config): static
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Validate the template structure for this type.
     */
    public function validate(PromptTemplate $template): bool
    {
        return true;
    }

    /**
     * Render variable substitutions in content.
     */
    protected function substituteVariables(string $content, array $variables, array $expected = []): string
    {
        $delimiters = config('prompt-templates.variable_delimiters', [
            'start' => '{{',
            'end' => '}}',
        ]);

        // Apply default values from expected variables
        foreach ($expected as $name => $config) {
            if (! array_key_exists($name, $variables) && array_key_exists('default', $config)) {
                $variables[$name] = $config['default'];
            }
        }

        $pattern = '/'.preg_quote($delimiters['start'], '/')
            .'\\s*([\\w.]+)\\s*'
            .preg_quote($delimiters['end'], '/').'/';

        return preg_replace_callback($pattern, function ($matches) use ($variables) {
            $key = $matches[1];

            // Support dot notation
            if (str_contains($key, '.')) {
                $value = data_get($variables, $key);
            } else {
                $value = $variables[$key] ?? null;
            }

            if ($value === null) {
                return $matches[0]; // Keep placeholder if no value
            }

            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            }

            return (string) $value;
        }, $content);
    }

    /**
     * Extract used variables from rendered content.
     */
    protected function extractUsedVariables(string $original, string $rendered, array $variables): array
    {
        $used = [];
        $delimiters = config('prompt-templates.variable_delimiters', [
            'start' => '{{',
            'end' => '}}',
        ]);

        $pattern = '/'.preg_quote($delimiters['start'], '/')
            .'\\s*([\\w.]+)\\s*'
            .preg_quote($delimiters['end'], '/').'/';

        preg_match_all($pattern, $original, $matches);

        foreach ($matches[1] ?? [] as $key) {
            if (array_key_exists($key, $variables) || data_get($variables, $key) !== null) {
                $used[$key] = data_get($variables, $key) ?? $variables[$key] ?? null;
            }
        }

        return $used;
    }
}
