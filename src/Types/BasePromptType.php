<?php

namespace Yannelli\PromptManager\Types;

use Yannelli\PromptManager\Contracts\PromptTypeInterface;
use Yannelli\PromptManager\Contracts\PromptRendererInterface;
use Yannelli\PromptManager\Contracts\VersionResolverInterface;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\Models\PromptComponent;
use Yannelli\PromptManager\Exceptions\RenderingException;

abstract class BasePromptType implements PromptTypeInterface
{
    public function __construct(
        protected PromptRendererInterface $renderer,
        protected VersionResolverInterface $versionResolver,
    ) {}

    abstract public function getRole(): string;

    /**
     * Make the type invokable
     */
    public function __invoke(PromptTemplate $template, PromptContext $context): RenderResult
    {
        return $this->render($template, $context);
    }

    public function render(PromptTemplate $template, PromptContext $context): RenderResult
    {
        $startTime = microtime(true);

        try {
            // Prepare context with type-specific modifications
            $context = $this->prepareContext($context);

            // Validate context
            $errors = $this->validateContext($context);
            if (!empty($errors)) {
                throw RenderingException::invalidContext($template->slug, $errors);
            }

            // Resolve the appropriate version
            $version = $this->versionResolver->resolve($template, $context);

            // Build content with components
            $content = $this->buildContent($template, $version, $context);

            // Render variables into content
            $rendered = $this->renderer->render($content, $this->prepareVariables($context));

            // Track used components
            $usedComponents = $this->getUsedComponents($template, $context);

            // Build result
            $result = RenderResult::make($rendered, $this->getRole(), [
                'template_slug' => $template->slug,
                'version_number' => $version->version_number,
                'used_components' => $usedComponents,
                'metadata' => [
                    'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                ],
            ]);

            // Post-process result
            return $this->postProcess($result);

        } catch (RenderingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw RenderingException::failed($template->slug, $e->getMessage(), $e);
        }
    }

    public function prepareContext(PromptContext $context): PromptContext
    {
        // Inject previous result as a variable if chaining
        if ($context->previousResult !== null) {
            return $context->withVariables([
                'previous_result' => $context->previousResult,
            ]);
        }

        return $context;
    }

    public function postProcess(RenderResult $result): RenderResult
    {
        return $result;
    }

    public function validateContext(PromptContext $context): array
    {
        return [];
    }

    protected function prepareVariables(PromptContext $context): array
    {
        return $context->variables;
    }

    protected function buildContent(
        PromptTemplate $template,
        PromptTemplateVersion $version,
        PromptContext $context
    ): string {
        $content = $version->content;
        $components = $template->components;

        // Sort components by order
        $sortedComponents = $components->sortBy('order');

        // Apply components in order
        foreach ($sortedComponents as $component) {
            if (!$this->isComponentEnabled($component, $context)) {
                continue;
            }

            $content = $this->applyComponent($content, $component);
        }

        return $content;
    }

    protected function applyComponent(string $content, PromptComponent $component): string
    {
        return match (true) {
            $component->isPrepend() => $component->content . "\n\n" . $content,
            $component->isAppend() => $content . "\n\n" . $component->content,
            $component->isReplace() => $this->replaceMarker($content, $component),
            default => $content,
        };
    }

    protected function replaceMarker(string $content, PromptComponent $component): string
    {
        $marker = $component->getReplaceMarker();

        if ($marker === null) {
            return $content;
        }

        // Support multiple marker formats
        $patterns = [
            "{{$marker}}",
            "{{ $marker }}",
            "{{{$marker}}}",
            "{{{ $marker }}}",
            "<!--{$marker}-->",
            "<!-- {$marker} -->",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return str_replace($pattern, $component->content, $content);
            }
        }

        return $content;
    }

    protected function isComponentEnabled(PromptComponent $component, PromptContext $context): bool
    {
        // Explicit disable takes precedence
        if (in_array($component->key, $context->disabledComponents, true)) {
            return false;
        }

        // Explicit enable overrides defaults
        if (in_array($component->key, $context->enabledComponents, true)) {
            return true;
        }

        // Fall back to component's conditional logic
        return $component->shouldBeEnabled($context->variables);
    }

    protected function getUsedComponents(PromptTemplate $template, PromptContext $context): array
    {
        return $template->components
            ->filter(fn (PromptComponent $c) => $this->isComponentEnabled($c, $context))
            ->pluck('key')
            ->toArray();
    }
}
