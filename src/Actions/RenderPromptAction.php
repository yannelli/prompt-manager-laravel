<?php

namespace Yannelli\PromptManager\Actions;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptExecution;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\Exceptions\TemplateNotFoundException;

class RenderPromptAction
{
    public function __construct(
        protected VersionResolverInterface $versionResolver
    ) {}

    public function __invoke(
        string|PromptTemplate $template,
        array|PromptContext $context = []
    ): RenderResult {
        return $this->handle($template, $context);
    }

    public function handle(
        string|PromptTemplate $template,
        array|PromptContext $context = []
    ): RenderResult {
        $startTime = microtime(true);

        // Resolve template
        $template = $this->resolveTemplate($template);

        // Normalize context
        $context = $this->normalizeContext($context);

        // Render using template's type handler
        $result = $template->typeHandler->render($template, $context);

        // Track execution if enabled
        $this->trackExecution($template, $context, $result, $startTime);

        return $result;
    }

    protected function resolveTemplate(string|PromptTemplate $template): PromptTemplate
    {
        if ($template instanceof PromptTemplate) {
            return $template;
        }

        $resolved = PromptTemplate::where('slug', $template)->active()->first();

        if (!$resolved) {
            throw TemplateNotFoundException::withSlug($template);
        }

        return $resolved;
    }

    protected function normalizeContext(array|PromptContext $context): PromptContext
    {
        if ($context instanceof PromptContext) {
            return $context;
        }

        return PromptContext::make($context);
    }

    protected function trackExecution(
        PromptTemplate $template,
        PromptContext $context,
        RenderResult $result,
        float $startTime
    ): void {
        if (!config('prompt-manager.track_executions', false)) {
            return;
        }

        $version = $this->versionResolver->resolve($template, $context);
        $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        PromptExecution::track($version, $context->variables, $result->content, [
            'enabled_components' => $result->usedComponents,
            'execution_time_ms' => $executionTimeMs,
            'user_id' => auth()->id(),
        ]);
    }
}
