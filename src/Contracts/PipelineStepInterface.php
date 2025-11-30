<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Contracts;

use PromptManager\PromptTemplates\DTOs\PipelineContext;

interface PipelineStepInterface
{
    /**
     * Execute this pipeline step.
     */
    public function handle(PipelineContext $context): PipelineContext;

    /**
     * Check if this step should be executed.
     */
    public function shouldExecute(PipelineContext $context): bool;

    /**
     * Get the name of this step.
     */
    public function getName(): string;

    /**
     * Get the order of this step in the pipeline.
     */
    public function getOrder(): int;
}
