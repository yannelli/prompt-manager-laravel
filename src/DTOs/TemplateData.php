<?php

namespace Yannelli\PromptManager\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class TemplateData implements Arrayable
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $content,
        public string $type = 'user',
        public ?string $description = null,
        public array $metadata = [],
        public array $variables = [],
        public array $components = [],
        public ?string $changeSummary = null,
        public ?int $createdBy = null,
    ) {}

    public static function make(array $data): self
    {
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            content: $data['content'],
            type: $data['type'] ?? 'user',
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? [],
            variables: $data['variables'] ?? [],
            components: $data['components'] ?? [],
            changeSummary: $data['change_summary'] ?? null,
            createdBy: $data['created_by'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'content' => $this->content,
            'type' => $this->type,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'variables' => $this->variables,
            'components' => $this->components,
            'change_summary' => $this->changeSummary,
            'created_by' => $this->createdBy,
        ];
    }
}
