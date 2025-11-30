<?php

namespace Yannelli\PromptManager\Actions;

use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\DTOs\TemplateData;
use Illuminate\Support\Facades\DB;

class CreateTemplateAction
{
    public function __invoke(array|TemplateData $data): PromptTemplate
    {
        return $this->handle($data);
    }

    public function handle(array|TemplateData $data): PromptTemplate
    {
        if ($data instanceof TemplateData) {
            $data = $data->toArray();
        }

        return DB::transaction(function () use ($data) {
            // Create the template
            $template = PromptTemplate::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? config('prompt-manager.default_type_key', 'user'),
                'metadata' => $data['metadata'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Create initial version if content is provided
            if (!empty($data['content'])) {
                $template->createVersion($data['content'], [
                    'variables' => $data['variables'] ?? null,
                    'component_config' => $data['component_config'] ?? null,
                    'change_summary' => $data['change_summary'] ?? 'Initial version',
                    'created_by' => $data['created_by'] ?? auth()->id(),
                    'is_published' => $data['is_published'] ?? config('prompt-manager.versioning.auto_publish', false),
                ]);
            }

            // Create components if provided
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
