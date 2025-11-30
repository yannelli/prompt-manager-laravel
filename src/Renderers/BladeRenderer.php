<?php

namespace Yannelli\PromptManager\Renderers;

use Yannelli\PromptManager\Contracts\PromptRendererInterface;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Throwable;

class BladeRenderer implements PromptRendererInterface
{
    protected bool $sandboxMode;

    public function __construct(bool $sandboxMode = true)
    {
        $this->sandboxMode = $sandboxMode;
    }

    public function render(string $template, array $variables = []): string
    {
        // Compile and render the Blade template
        try {
            $compiled = Blade::compileString($template);

            // Create a temporary file for execution
            $tempPath = $this->createTempFile($compiled);

            try {
                // Extract variables and render
                return $this->executeTemplate($tempPath, $variables);
            } finally {
                // Clean up temp file
                @unlink($tempPath);
            }
        } catch (Throwable $e) {
            throw new \RuntimeException(
                "Failed to render Blade template: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    protected function createTempFile(string $content): string
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/prompt_manager_' . Str::random(16) . '.php';

        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    protected function executeTemplate(string $path, array $variables): string
    {
        // Use output buffering to capture the rendered output
        ob_start();

        try {
            // Extract variables into local scope
            extract($variables, EXTR_SKIP);

            // Include and execute the template
            include $path;

            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public function supports(string $template): bool
    {
        // Check for Blade-specific syntax
        return str_contains($template, '@') ||
               str_contains($template, '{{') ||
               str_contains($template, '{!!') ||
               str_contains($template, '@php') ||
               str_contains($template, '@if') ||
               str_contains($template, '@foreach') ||
               str_contains($template, '@include');
    }

    public function setSandboxMode(bool $enabled): self
    {
        $this->sandboxMode = $enabled;
        return $this;
    }
}
