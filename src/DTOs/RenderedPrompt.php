<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class RenderedPrompt implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly ?string $systemPrompt = null,
        public readonly ?string $userPrompt = null,
        public readonly ?string $assistantPrompt = null,
        public readonly ?string $content = null,
        public readonly array $messages = [],
        public readonly array $metadata = [],
        public readonly ?string $templateId = null,
        public readonly ?int $version = null,
        public readonly array $usedVariables = [],
    ) {}

    /**
     * Create from a chat-style template.
     */
    public static function fromChat(
        ?string $system = null,
        ?string $user = null,
        ?string $assistant = null,
        array $metadata = []
    ): self {
        $messages = [];

        if ($system !== null) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        if ($user !== null) {
            $messages[] = ['role' => 'user', 'content' => $user];
        }
        if ($assistant !== null) {
            $messages[] = ['role' => 'assistant', 'content' => $assistant];
        }

        return new self(
            systemPrompt: $system,
            userPrompt: $user,
            assistantPrompt: $assistant,
            messages: $messages,
            metadata: $metadata,
        );
    }

    /**
     * Create from a completion-style template.
     */
    public static function fromCompletion(string $content, array $metadata = []): self
    {
        return new self(
            content: $content,
            metadata: $metadata,
        );
    }

    /**
     * Get as messages array for chat APIs.
     */
    public function toMessages(): array
    {
        if (! empty($this->messages)) {
            return $this->messages;
        }

        $messages = [];

        if ($this->systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }
        if ($this->userPrompt !== null) {
            $messages[] = ['role' => 'user', 'content' => $this->userPrompt];
        }
        if ($this->assistantPrompt !== null) {
            $messages[] = ['role' => 'assistant', 'content' => $this->assistantPrompt];
        }

        return $messages;
    }

    /**
     * Get as a single string (for completion APIs).
     */
    public function toString(): string
    {
        if ($this->content !== null) {
            return $this->content;
        }

        $parts = array_filter([
            $this->systemPrompt,
            $this->userPrompt,
            $this->assistantPrompt,
        ]);

        return implode("\n\n", $parts);
    }

    /**
     * Merge with another rendered prompt.
     */
    public function merge(RenderedPrompt $other): self
    {
        return new self(
            systemPrompt: $this->mergeContent($this->systemPrompt, $other->systemPrompt),
            userPrompt: $this->mergeContent($this->userPrompt, $other->userPrompt),
            assistantPrompt: $this->mergeContent($this->assistantPrompt, $other->assistantPrompt),
            content: $this->mergeContent($this->content, $other->content),
            messages: array_merge($this->messages, $other->messages),
            metadata: array_merge($this->metadata, $other->metadata),
            templateId: $other->templateId ?? $this->templateId,
            version: $other->version ?? $this->version,
            usedVariables: array_merge($this->usedVariables, $other->usedVariables),
        );
    }

    private function mergeContent(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return $a."\n\n".$b;
    }

    public function toArray(): array
    {
        return [
            'system_prompt' => $this->systemPrompt,
            'user_prompt' => $this->userPrompt,
            'assistant_prompt' => $this->assistantPrompt,
            'content' => $this->content,
            'messages' => $this->messages,
            'metadata' => $this->metadata,
            'template_id' => $this->templateId,
            'version' => $this->version,
            'used_variables' => $this->usedVariables,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
