<?php

namespace Yannelli\PromptManager\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class PromptContext implements Arrayable
{
    public function __construct(
        public array $variables = [],
        public array $enabledComponents = [],
        public array $disabledComponents = [],
        public ?int $version = null,
        public array $metadata = [],
        public ?string $previousResult = null,
    ) {}

    public static function make(array $data = []): self
    {
        return new self(
            variables: $data['variables'] ?? [],
            enabledComponents: $data['enabled_components'] ?? [],
            disabledComponents: $data['disabled_components'] ?? [],
            version: $data['version'] ?? null,
            metadata: $data['metadata'] ?? [],
            previousResult: $data['previous_result'] ?? null,
        );
    }

    public function withVariables(array $variables): self
    {
        return new self(
            variables: array_merge($this->variables, $variables),
            enabledComponents: $this->enabledComponents,
            disabledComponents: $this->disabledComponents,
            version: $this->version,
            metadata: $this->metadata,
            previousResult: $this->previousResult,
        );
    }

    public function withPreviousResult(string $result): self
    {
        return new self(
            variables: $this->variables,
            enabledComponents: $this->enabledComponents,
            disabledComponents: $this->disabledComponents,
            version: $this->version,
            metadata: $this->metadata,
            previousResult: $result,
        );
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            variables: $this->variables,
            enabledComponents: $this->enabledComponents,
            disabledComponents: $this->disabledComponents,
            version: $this->version,
            metadata: array_merge($this->metadata, $metadata),
            previousResult: $this->previousResult,
        );
    }

    public function withVersion(?int $version): self
    {
        return new self(
            variables: $this->variables,
            enabledComponents: $this->enabledComponents,
            disabledComponents: $this->disabledComponents,
            version: $version,
            metadata: $this->metadata,
            previousResult: $this->previousResult,
        );
    }

    public function enableComponents(array $keys): self
    {
        return new self(
            variables: $this->variables,
            enabledComponents: array_unique(array_merge($this->enabledComponents, $keys)),
            disabledComponents: array_diff($this->disabledComponents, $keys),
            version: $this->version,
            metadata: $this->metadata,
            previousResult: $this->previousResult,
        );
    }

    public function disableComponents(array $keys): self
    {
        return new self(
            variables: $this->variables,
            enabledComponents: array_diff($this->enabledComponents, $keys),
            disabledComponents: array_unique(array_merge($this->disabledComponents, $keys)),
            version: $this->version,
            metadata: $this->metadata,
            previousResult: $this->previousResult,
        );
    }

    public function toArray(): array
    {
        return [
            'variables' => $this->variables,
            'enabled_components' => $this->enabledComponents,
            'disabled_components' => $this->disabledComponents,
            'version' => $this->version,
            'metadata' => $this->metadata,
            'previous_result' => $this->previousResult,
        ];
    }
}
