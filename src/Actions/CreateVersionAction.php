<?php

namespace Yannelli\PromptManager\Actions;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\Exceptions\TemplateNotFoundException;

class CreateVersionAction
{
    public function __invoke(
        string|PromptTemplate $template,
        string $content,
        array $options = []
    ): PromptTemplateVersion {
        return $this->handle($template, $content, $options);
    }

    public function handle(
        string|PromptTemplate $template,
        string $content,
        array $options = []
    ): PromptTemplateVersion {
        $template = $this->resolveTemplate($template);

        $version = $template->createVersion($content, [
            'variables' => $options['variables'] ?? null,
            'component_config' => $options['component_config'] ?? null,
            'mapping_rules' => $options['mapping_rules'] ?? null,
            'change_summary' => $options['change_summary'] ?? null,
            'created_by' => $options['created_by'] ?? auth()->id(),
            'set_as_current' => $options['set_as_current'] ?? true,
            'is_published' => $options['is_published'] ?? config('prompt-manager.versioning.auto_publish', false),
        ]);

        // Auto-publish if configured
        if ($options['publish'] ?? false) {
            $version->publish();
        }

        return $version;
    }

    protected function resolveTemplate(string|PromptTemplate $template): PromptTemplate
    {
        if ($template instanceof PromptTemplate) {
            return $template;
        }

        $resolved = PromptTemplate::where('slug', $template)->first();

        if (!$resolved) {
            throw TemplateNotFoundException::withSlug($template);
        }

        return $resolved;
    }
}
