<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class VersionMappingResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly bool $success,
        public readonly array $content,
        public readonly array $variables = [],
        public readonly int $fromVersion = 0,
        public readonly int $toVersion = 0,
        public readonly array $warnings = [],
        public readonly array $transformations = [],
        public readonly ?string $error = null,
    ) {}

    /**
     * Create a successful mapping result.
     */
    public static function success(
        array $content,
        array $variables = [],
        int $fromVersion = 0,
        int $toVersion = 0,
        array $transformations = [],
        array $warnings = []
    ): self {
        return new self(
            success: true,
            content: $content,
            variables: $variables,
            fromVersion: $fromVersion,
            toVersion: $toVersion,
            warnings: $warnings,
            transformations: $transformations,
        );
    }

    /**
     * Create a failed mapping result.
     */
    public static function failure(string $error, int $fromVersion = 0, int $toVersion = 0): self
    {
        return new self(
            success: false,
            content: [],
            fromVersion: $fromVersion,
            toVersion: $toVersion,
            error: $error,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'content' => $this->content,
            'variables' => $this->variables,
            'from_version' => $this->fromVersion,
            'to_version' => $this->toVersion,
            'warnings' => $this->warnings,
            'transformations' => $this->transformations,
            'error' => $this->error,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
