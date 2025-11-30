<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use JsonSerializable;

final class PipelineContext implements Arrayable, JsonSerializable
{
    private array $data = [];

    private array $results = [];

    private array $errors = [];

    private bool $shouldContinue = true;

    public function __construct(
        public readonly array $initialInput = [],
        public readonly array $config = [],
    ) {
        $this->data = $initialInput;
    }

    /**
     * Get a value from the context.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * Set a value in the context.
     */
    public function set(string $key, mixed $value): self
    {
        Arr::set($this->data, $key, $value);

        return $this;
    }

    /**
     * Check if a key exists in the context.
     */
    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * Get all context data.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Merge data into the context.
     */
    public function merge(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Add a step result.
     */
    public function addResult(string $stepName, RenderedPrompt $result): self
    {
        $this->results[$stepName] = $result;
        $this->set("results.{$stepName}", $result->toArray());

        return $this;
    }

    /**
     * Get a step result.
     */
    public function getResult(string $stepName): ?RenderedPrompt
    {
        return $this->results[$stepName] ?? null;
    }

    /**
     * Get all step results.
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get the last result.
     */
    public function getLastResult(): ?RenderedPrompt
    {
        if (empty($this->results)) {
            return null;
        }

        return end($this->results);
    }

    /**
     * Add an error.
     */
    public function addError(string $stepName, string $message, array $context = []): self
    {
        $this->errors[$stepName] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
        ];

        return $this;
    }

    /**
     * Get all errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Stop pipeline execution.
     */
    public function stop(): self
    {
        $this->shouldContinue = false;

        return $this;
    }

    /**
     * Check if pipeline should continue.
     */
    public function shouldContinue(): bool
    {
        return $this->shouldContinue;
    }

    /**
     * Create a new context with additional input.
     */
    public function withInput(array $input): self
    {
        $new = new self(
            initialInput: array_merge($this->initialInput, $input),
            config: $this->config,
        );
        $new->data = array_merge($this->data, $input);
        $new->results = $this->results;
        $new->errors = $this->errors;
        $new->shouldContinue = $this->shouldContinue;

        return $new;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'results' => array_map(fn ($r) => $r->toArray(), $this->results),
            'errors' => $this->errors,
            'should_continue' => $this->shouldContinue,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
