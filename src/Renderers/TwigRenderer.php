<?php

namespace Yannelli\PromptManager\Renderers;

use Yannelli\PromptManager\Contracts\PromptRendererInterface;

class TwigRenderer implements PromptRendererInterface
{
    protected ?object $twig = null;

    public function __construct()
    {
        if (class_exists('\Twig\Environment')) {
            $loader = new \Twig\Loader\ArrayLoader();
            $this->twig = new \Twig\Environment($loader, [
                'autoescape' => false,
            ]);
        }
    }

    public function render(string $template, array $variables = []): string
    {
        if (!$this->twig) {
            throw new \RuntimeException(
                'Twig is not installed. Install it via: composer require twig/twig'
            );
        }

        $templateObj = $this->twig->createTemplate($template);
        return $templateObj->render($variables);
    }

    public function supports(string $template): bool
    {
        if (!$this->twig) {
            return false;
        }

        // Check for Twig-specific syntax
        return str_contains($template, '{%') ||
               str_contains($template, '{{') ||
               str_contains($template, '{#');
    }
}
