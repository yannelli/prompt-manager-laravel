<?php

namespace Yannelli\PromptManager\Renderers;

use Yannelli\PromptManager\Contracts\PromptRendererInterface;

class ChainedRenderer implements PromptRendererInterface
{
    /** @var PromptRendererInterface[] */
    protected array $renderers = [];

    protected PromptRendererInterface $fallback;

    public function __construct(?PromptRendererInterface $fallback = null)
    {
        $this->fallback = $fallback ?? new SimpleRenderer();
    }

    public function addRenderer(PromptRendererInterface $renderer): self
    {
        $this->renderers[] = $renderer;
        return $this;
    }

    public function prependRenderer(PromptRendererInterface $renderer): self
    {
        array_unshift($this->renderers, $renderer);
        return $this;
    }

    public function render(string $template, array $variables = []): string
    {
        // Find the first renderer that supports this template
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($template)) {
                return $renderer->render($template, $variables);
            }
        }

        // Use fallback
        return $this->fallback->render($template, $variables);
    }

    public function supports(string $template): bool
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($template)) {
                return true;
            }
        }

        return $this->fallback->supports($template);
    }

    public function getRenderers(): array
    {
        return $this->renderers;
    }

    public function setFallback(PromptRendererInterface $renderer): self
    {
        $this->fallback = $renderer;
        return $this;
    }
}
