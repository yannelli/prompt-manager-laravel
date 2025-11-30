<?php

namespace Yannelli\PromptManager\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class RenderResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $content,
        public string $role,
        public array $metadata = [],
        public ?string $templateSlug = null,
        public ?int $versionNumber = null,
        public array $usedComponents = [],
    ) {}

    public static function make(string $content, string $role, array $options = []): self
    {
        return new self(
            content: $content,
            role: $role,
            metadata: $options['metadata'] ?? [],
            templateSlug: $options['template_slug'] ?? null,
            versionNumber: $options['version_number'] ?? null,
            usedComponents: $options['used_components'] ?? [],
        );
    }

    public function withContent(string $content): self
    {
        return new self(
            content: $content,
            role: $this->role,
            metadata: $this->metadata,
            templateSlug: $this->templateSlug,
            versionNumber: $this->versionNumber,
            usedComponents: $this->usedComponents,
        );
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            content: $this->content,
            role: $this->role,
            metadata: array_merge($this->metadata, $metadata),
            templateSlug: $this->templateSlug,
            versionNumber: $this->versionNumber,
            usedComponents: $this->usedComponents,
        );
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'role' => $this->role,
            'metadata' => $this->metadata,
            'template_slug' => $this->templateSlug,
            'version_number' => $this->versionNumber,
            'used_components' => $this->usedComponents,
        ];
    }

    public function toMessage(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
