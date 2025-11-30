<?php

namespace Yannelli\PromptManager\Pipelines\Pipes;

use Closure;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\Contracts\PipeInterface;

class InjectMetadataPipe implements PipeInterface
{
    protected array $metadata;
    protected ?Closure $metadataResolver;

    public function __construct(array $metadata = [], ?Closure $metadataResolver = null)
    {
        $this->metadata = $metadata;
        $this->metadataResolver = $metadataResolver;
    }

    public function handle(PromptContext $context, Closure $next): PromptContext
    {
        $metadata = $this->metadata;

        // Resolve dynamic metadata if resolver is provided
        if ($this->metadataResolver) {
            $dynamicMetadata = ($this->metadataResolver)($context);
            $metadata = array_merge($metadata, $dynamicMetadata);
        }

        return $next($context->withMetadata($metadata));
    }

    public static function withTimestamp(): self
    {
        return new self([], function () {
            return [
                'processed_at' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
            ];
        });
    }

    public static function withUser(?int $userId = null): self
    {
        return new self([], function () use ($userId) {
            return [
                'user_id' => $userId ?? auth()->id(),
                'user_type' => auth()->check() ? 'authenticated' : 'guest',
            ];
        });
    }
}
