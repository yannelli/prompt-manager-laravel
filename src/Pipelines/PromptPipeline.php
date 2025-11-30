<?php

namespace Yannelli\PromptManager\Pipelines;

use Illuminate\Pipeline\Pipeline;
use Yannelli\PromptManager\DTOs\PromptContext;
use Yannelli\PromptManager\DTOs\RenderResult;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Exceptions\TemplateNotFoundException;
use Closure;

class PromptPipeline
{
    protected array $templates = [];
    protected array $pipes = [];
    protected Pipeline $pipeline;
    protected ?Closure $onRender = null;
    protected ?Closure $onError = null;

    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Add a template to the chain
     */
    public function template(string|PromptTemplate $template, array $contextOverrides = []): self
    {
        $this->templates[] = [
            'template' => $template,
            'context_overrides' => $contextOverrides,
        ];

        return $this;
    }

    /**
     * Add multiple templates to the chain
     */
    public function templates(array $templates): self
    {
        foreach ($templates as $template) {
            if (is_array($template)) {
                $this->template($template['template'] ?? $template[0], $template['context'] ?? $template[1] ?? []);
            } else {
                $this->template($template);
            }
        }

        return $this;
    }

    /**
     * Add a custom pipe to process context before each template
     */
    public function pipe(callable|string $pipe): self
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    /**
     * Add multiple pipes
     */
    public function through(array $pipes): self
    {
        foreach ($pipes as $pipe) {
            $this->pipe($pipe);
        }
        return $this;
    }

    /**
     * Set callback for each render
     */
    public function onRender(Closure $callback): self
    {
        $this->onRender = $callback;
        return $this;
    }

    /**
     * Set error handler
     */
    public function onError(Closure $callback): self
    {
        $this->onError = $callback;
        return $this;
    }

    /**
     * Execute the pipeline
     *
     * @return RenderResult|array<RenderResult>
     */
    public function run(PromptContext $context, bool $collectAll = false): RenderResult|array
    {
        $results = [];
        $currentContext = $context;
        $chainInfo = [];

        foreach ($this->templates as $index => $item) {
            try {
                $template = $this->resolveTemplate($item['template']);

                // Apply context overrides for this specific template
                $templateContext = $currentContext;
                if (!empty($item['context_overrides'])) {
                    $templateContext = $currentContext->withVariables($item['context_overrides']);
                }

                // Run through custom pipes if any
                if (!empty($this->pipes)) {
                    $templateContext = $this->pipeline
                        ->send($templateContext)
                        ->through($this->pipes)
                        ->thenReturn();
                }

                // Render
                $result = $template->typeHandler->render($template, $templateContext);

                // Track chain info
                $chainInfo[] = [
                    'template' => $template->slug,
                    'version' => $result->versionNumber,
                    'index' => $index,
                ];

                // Add chain metadata to result
                $result = $result->withMetadata([
                    'chain_index' => $index,
                    'chain_length' => count($this->templates),
                ]);

                $results[] = $result;

                // Call render callback if set
                if ($this->onRender) {
                    ($this->onRender)($result, $index, $template);
                }

                // Pass result to next iteration
                $currentContext = $currentContext->withPreviousResult($result->content);

            } catch (\Throwable $e) {
                if ($this->onError) {
                    $shouldContinue = ($this->onError)($e, $index, $item);
                    if ($shouldContinue === false) {
                        throw $e;
                    }
                    continue;
                }
                throw $e;
            }
        }

        if (empty($results)) {
            throw new \RuntimeException('Pipeline produced no results. Ensure at least one template is added.');
        }

        return $collectAll ? $results : end($results);
    }

    /**
     * Run pipeline and return all results as messages array
     */
    public function toMessages(PromptContext $context): array
    {
        $results = $this->run($context, collectAll: true);

        return array_map(fn (RenderResult $r) => $r->toMessage(), $results);
    }

    /**
     * Run pipeline and return concatenated content
     */
    public function toString(PromptContext $context, string $separator = "\n\n"): string
    {
        $results = $this->run($context, collectAll: true);

        return implode($separator, array_map(fn (RenderResult $r) => $r->content, $results));
    }

    /**
     * Get the number of templates in the pipeline
     */
    public function count(): int
    {
        return count($this->templates);
    }

    /**
     * Check if pipeline is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->templates);
    }

    /**
     * Clear all templates and pipes
     */
    public function clear(): self
    {
        $this->templates = [];
        $this->pipes = [];
        return $this;
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
}
