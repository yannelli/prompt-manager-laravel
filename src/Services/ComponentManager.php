<?php

declare(strict_types=1);

namespace PromptManager\PromptTemplates\Services;

use Illuminate\Support\Collection;
use PromptManager\PromptTemplates\Models\PromptComponent;
use PromptManager\PromptTemplates\Models\PromptTemplate;

class ComponentManager
{
    /**
     * Attach a component to a template.
     */
    public function attach(
        PromptTemplate $template,
        PromptComponent $component,
        array $options = []
    ): void {
        $template->components()->attach($component->id, [
            'user_id' => $options['user_id'] ?? null,
            'is_enabled' => $options['is_enabled'] ?? config('prompt-templates.components.default_enabled', true),
            'order' => $options['order'] ?? $this->getNextOrder($template, $options['user_id'] ?? null),
            'target' => $options['target'] ?? 'user_prompt',
            'position' => $options['position'] ?? $component->position,
            'config' => $options['config'] ?? null,
            'variable_overrides' => $options['variable_overrides'] ?? null,
            'conditions' => $options['conditions'] ?? null,
        ]);
    }

    /**
     * Detach a component from a template.
     */
    public function detach(
        PromptTemplate $template,
        PromptComponent $component,
        ?int $userId = null
    ): void {
        $query = $template->components()->wherePivot('prompt_component_id', $component->id);

        if ($userId !== null) {
            $query->wherePivot('user_id', $userId);
        } else {
            $query->wherePivotNull('user_id');
        }

        $query->detach($component->id);
    }

    /**
     * Enable a component for a template.
     */
    public function enable(
        PromptTemplate $template,
        PromptComponent $component,
        ?int $userId = null
    ): void {
        $this->updatePivot($template, $component, $userId, ['is_enabled' => true]);
    }

    /**
     * Disable a component for a template.
     */
    public function disable(
        PromptTemplate $template,
        PromptComponent $component,
        ?int $userId = null
    ): void {
        $this->updatePivot($template, $component, $userId, ['is_enabled' => false]);
    }

    /**
     * Toggle a component's enabled state.
     */
    public function toggle(
        PromptTemplate $template,
        PromptComponent $component,
        ?int $userId = null
    ): bool {
        $currentState = $this->isEnabled($template, $component, $userId);
        $newState = ! $currentState;

        $this->updatePivot($template, $component, $userId, ['is_enabled' => $newState]);

        return $newState;
    }

    /**
     * Check if a component is enabled for a template.
     */
    public function isEnabled(
        PromptTemplate $template,
        PromptComponent $component,
        ?int $userId = null
    ): bool {
        $query = $template->components()
            ->where('prompt_component_id', $component->id);

        if ($userId !== null) {
            $query->wherePivot('user_id', $userId);
        } else {
            $query->wherePivotNull('user_id');
        }

        $attachment = $query->first();

        return $attachment ? (bool) $attachment->pivot->is_enabled : false;
    }

    /**
     * Update component order.
     */
    public function reorder(
        PromptTemplate $template,
        array $componentIds,
        ?int $userId = null
    ): void {
        foreach ($componentIds as $order => $componentId) {
            $this->updatePivotById($template, $componentId, $userId, ['order' => $order]);
        }
    }

    /**
     * Get enabled components for a template.
     */
    public function getEnabled(PromptTemplate $template, ?int $userId = null): Collection
    {
        $query = $template->components()->wherePivot('is_enabled', true);

        if ($userId !== null) {
            // Get user-specific settings, falling back to global settings
            $query->where(function ($q) use ($userId) {
                $q->wherePivot('user_id', $userId)
                    ->orWherePivotNull('user_id');
            });
        } else {
            $query->wherePivotNull('user_id');
        }

        return $query->orderByPivot('order')->get();
    }

    /**
     * Get all components for a template with their attachment settings.
     */
    public function getAllWithSettings(PromptTemplate $template, ?int $userId = null): Collection
    {
        $query = $template->components();

        if ($userId !== null) {
            $query->where(function ($q) use ($userId) {
                $q->wherePivot('user_id', $userId)
                    ->orWherePivotNull('user_id');
            });
        }

        return $query->orderByPivot('order')->get();
    }

    /**
     * Copy component settings from one user to another.
     */
    public function copyUserSettings(
        PromptTemplate $template,
        int $fromUserId,
        int $toUserId
    ): void {
        $components = $template->components()
            ->wherePivot('user_id', $fromUserId)
            ->get();

        foreach ($components as $component) {
            // Check if target user already has settings
            $existing = $template->components()
                ->wherePivot('prompt_component_id', $component->id)
                ->wherePivot('user_id', $toUserId)
                ->exists();

            if (! $existing) {
                $template->components()->attach($component->id, [
                    'user_id' => $toUserId,
                    'is_enabled' => $component->pivot->is_enabled,
                    'order' => $component->pivot->order,
                    'target' => $component->pivot->target,
                    'position' => $component->pivot->position,
                    'config' => $component->pivot->config,
                    'variable_overrides' => $component->pivot->variable_overrides,
                    'conditions' => $component->pivot->conditions,
                ]);
            }
        }
    }

    /**
     * Reset user settings to global defaults.
     */
    public function resetUserSettings(PromptTemplate $template, int $userId): void
    {
        $template->components()->wherePivot('user_id', $userId)->detach();
    }

    /**
     * Update component configuration.
     */
    public function updateConfig(
        PromptTemplate $template,
        PromptComponent $component,
        array $config,
        ?int $userId = null
    ): void {
        $this->updatePivot($template, $component, $userId, ['config' => $config]);
    }

    /**
     * Set variable overrides for a component.
     */
    public function setVariableOverrides(
        PromptTemplate $template,
        PromptComponent $component,
        array $overrides,
        ?int $userId = null
    ): void {
        $this->updatePivot($template, $component, $userId, ['variable_overrides' => $overrides]);
    }

    /**
     * Set conditions for a component.
     */
    public function setConditions(
        PromptTemplate $template,
        PromptComponent $component,
        array $conditions,
        ?int $userId = null
    ): void {
        $this->updatePivot($template, $component, $userId, ['conditions' => $conditions]);
    }

    /**
     * Get the next order number for a template's components.
     */
    protected function getNextOrder(PromptTemplate $template, ?int $userId = null): int
    {
        $query = $template->components();

        if ($userId !== null) {
            $query->wherePivot('user_id', $userId);
        } else {
            $query->wherePivotNull('user_id');
        }

        $maxOrder = $query->max('prompt_template_components.order') ?? -1;

        return $maxOrder + 1;
    }

    /**
     * Update pivot table record.
     */
    protected function updatePivot(
        PromptTemplate $template,
        PromptComponent $component,
        ?int $userId,
        array $attributes
    ): void {
        $this->updatePivotById($template, $component->id, $userId, $attributes);
    }

    /**
     * Update pivot table record by component ID.
     */
    protected function updatePivotById(
        PromptTemplate $template,
        int $componentId,
        ?int $userId,
        array $attributes
    ): void {
        $query = $template->components()
            ->newPivotStatement()
            ->where('prompt_template_id', $template->id)
            ->where('prompt_component_id', $componentId);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        $query->update($attributes);
    }
}
