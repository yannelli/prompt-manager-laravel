<?php

namespace Yannelli\PromptManager\Actions;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Exceptions\TemplateNotFoundException;

class ExportTemplateAction
{
    public function __invoke(
        string|PromptTemplate $template,
        bool $includeAllVersions = false
    ): array {
        return $this->handle($template, $includeAllVersions);
    }

    public function handle(
        string|PromptTemplate $template,
        bool $includeAllVersions = false
    ): array {
        $template = $this->resolveTemplate($template);

        $export = [
            'slug' => $template->slug,
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type,
            'metadata' => $template->metadata,
            'is_active' => $template->is_active,
            'components' => $template->components->map(fn ($c) => [
                'key' => $c->key,
                'name' => $c->name,
                'content' => $c->content,
                'position' => $c->position,
                'order' => $c->order,
                'is_default_enabled' => $c->is_default_enabled,
                'conditions' => $c->conditions,
            ])->toArray(),
            'exported_at' => now()->toIso8601String(),
            'export_version' => '1.0',
        ];

        if ($includeAllVersions) {
            $export['versions'] = $template->versions->map(fn ($v) => [
                'version_number' => $v->version_number,
                'content' => $v->content,
                'variables' => $v->variables,
                'component_config' => $v->component_config,
                'mapping_rules' => $v->mapping_rules,
                'change_summary' => $v->change_summary,
                'is_published' => $v->is_published,
                'published_at' => $v->published_at?->toIso8601String(),
                'created_at' => $v->created_at->toIso8601String(),
            ])->toArray();
        } else {
            $currentVersion = $template->getEffectiveVersion();
            if ($currentVersion) {
                $export['content'] = $currentVersion->content;
                $export['variables'] = $currentVersion->variables;
                $export['component_config'] = $currentVersion->component_config;
                $export['mapping_rules'] = $currentVersion->mapping_rules;
            }
        }

        return $export;
    }

    protected function resolveTemplate(string|PromptTemplate $template): PromptTemplate
    {
        if ($template instanceof PromptTemplate) {
            return $template->load(['versions', 'components']);
        }

        $resolved = PromptTemplate::where('slug', $template)
            ->with(['versions', 'components'])
            ->first();

        if (!$resolved) {
            throw TemplateNotFoundException::withSlug($template);
        }

        return $resolved;
    }
}
