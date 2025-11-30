<?php

namespace Yannelli\PromptManager\Actions;

use Yannelli\PromptManager\Models\PromptTemplate;
use Illuminate\Support\Facades\DB;

class ImportTemplateAction
{
    public function __invoke(array $data, bool $overwrite = false): PromptTemplate
    {
        return $this->handle($data, $overwrite);
    }

    public function handle(array $data, bool $overwrite = false): PromptTemplate
    {
        return DB::transaction(function () use ($data, $overwrite) {
            $existingTemplate = PromptTemplate::where('slug', $data['slug'])->first();

            if ($existingTemplate) {
                if (!$overwrite) {
                    throw new \RuntimeException(
                        "Template with slug '{$data['slug']}' already exists. Set overwrite=true to replace."
                    );
                }

                // Delete existing template (will cascade to versions and components)
                $existingTemplate->forceDelete();
            }

            // Create the template
            $template = PromptTemplate::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'user',
                'metadata' => $data['metadata'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Import versions if available
            if (!empty($data['versions'])) {
                foreach ($data['versions'] as $versionData) {
                    $version = $template->createVersion($versionData['content'], [
                        'variables' => $versionData['variables'] ?? null,
                        'component_config' => $versionData['component_config'] ?? null,
                        'mapping_rules' => $versionData['mapping_rules'] ?? null,
                        'change_summary' => $versionData['change_summary'] ?? 'Imported',
                        'is_published' => $versionData['is_published'] ?? false,
                        'set_as_current' => false,
                    ]);

                    if ($versionData['is_published'] ?? false) {
                        $version->publish();
                    }
                }

                // Set the highest version as current
                $latestVersion = $template->versions()->orderByDesc('version_number')->first();
                if ($latestVersion) {
                    $template->update(['current_version_id' => $latestVersion->id]);
                }
            } elseif (!empty($data['content'])) {
                // Single version import
                $template->createVersion($data['content'], [
                    'variables' => $data['variables'] ?? null,
                    'component_config' => $data['component_config'] ?? null,
                    'mapping_rules' => $data['mapping_rules'] ?? null,
                    'change_summary' => 'Imported',
                ]);
            }

            // Import components
            if (!empty($data['components'])) {
                foreach ($data['components'] as $index => $component) {
                    $template->addComponent([
                        'key' => $component['key'],
                        'name' => $component['name'] ?? $component['key'],
                        'content' => $component['content'],
                        'position' => $component['position'] ?? 'append',
                        'order' => $component['order'] ?? $index,
                        'is_default_enabled' => $component['is_default_enabled'] ?? true,
                        'conditions' => $component['conditions'] ?? null,
                    ]);
                }
            }

            return $template->fresh(['currentVersion', 'components', 'versions']);
        });
    }
}
