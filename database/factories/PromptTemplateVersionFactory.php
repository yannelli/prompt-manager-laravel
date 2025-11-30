<?php

namespace Yannelli\PromptManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptTemplateVersion;

class PromptTemplateVersionFactory extends Factory
{
    protected $model = PromptTemplateVersion::class;

    public function definition(): array
    {
        return [
            'prompt_template_id' => PromptTemplate::factory(),
            'version_number' => 1,
            'content' => $this->faker->paragraph() . "\n\n{{ variable }}",
            'variables' => ['variable'],
            'component_config' => null,
            'mapping_rules' => null,
            'change_summary' => $this->faker->optional()->sentence(),
            'created_by' => null,
            'is_published' => false,
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the version is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the version is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Set specific version number.
     */
    public function versionNumber(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'version_number' => $number,
        ]);
    }

    /**
     * Add variables schema.
     */
    public function withVariables(array $variables): static
    {
        return $this->state(fn (array $attributes) => [
            'variables' => $variables,
        ]);
    }

    /**
     * Add mapping rules for version resolution.
     */
    public function withMappingRules(array $rules): static
    {
        return $this->state(fn (array $attributes) => [
            'mapping_rules' => $rules,
        ]);
    }

    /**
     * Set created by user.
     */
    public function createdBy(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $userId,
        ]);
    }

    /**
     * Create with specific content.
     */
    public function withContent(string $content): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $content,
        ]);
    }
}
