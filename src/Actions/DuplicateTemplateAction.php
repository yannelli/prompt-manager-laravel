<?php

namespace Yannelli\PromptManager\Actions;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Exceptions\TemplateNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateTemplateAction
{
    public function __invoke(
        string|PromptTemplate $template,
        ?string $newSlug = null,
        ?string $newName = null
    ): PromptTemplate {
        return $this->handle($template, $newSlug, $newName);
    }

    public function handle(
        string|PromptTemplate $template,
        ?string $newSlug = null,
        ?string $newName = null
    ): PromptTemplate {
        $source = $this->resolveTemplate($template);

        return DB::transaction(function () use ($source, $newSlug, $newName) {
            // Generate unique slug if not provided
            $slug = $newSlug ?? $this->generateUniqueSlug($source->slug);
            $name = $newName ?? $source->name . ' (Copy)';

            // Create the new template
            $duplicate = PromptTemplate::create([
                'slug' => $slug,
                'name' => $name,
                'description' => $source->description,
                'type' => $source->type,
                'metadata' => $source->metadata,
                'is_active' => true,
            ]);

            // Duplicate the current version
            $currentVersion = $source->getEffectiveVersion();
            if ($currentVersion) {
                $duplicate->createVersion($currentVersion->content, [
                    'variables' => $currentVersion->variables,
                    'component_config' => $currentVersion->component_config,
                    'mapping_rules' => $currentVersion->mapping_rules,
                    'change_summary' => "Duplicated from {$source->slug} version {$currentVersion->version_number}",
                    'created_by' => auth()->id(),
                ]);
            }

            // Duplicate components
            foreach ($source->components as $component) {
                $duplicate->addComponent([
                    'key' => $component->key,
                    'name' => $component->name,
                    'content' => $component->content,
                    'position' => $component->position,
                    'order' => $component->order,
                    'is_default_enabled' => $component->is_default_enabled,
                    'conditions' => $component->conditions,
                ]);
            }

            return $duplicate->fresh(['currentVersion', 'components']);
        });
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

    protected function generateUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug . '-copy';
        $counter = 1;

        while (PromptTemplate::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-copy-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
