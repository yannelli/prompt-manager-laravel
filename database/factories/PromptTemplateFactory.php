<?php

namespace Yannelli\PromptManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Yannelli\PromptManager\Models\PromptTemplate;

class PromptTemplateFactory extends Factory
{
    protected $model = PromptTemplate::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(3),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'type' => $this->faker->randomElement(['system', 'user', 'assistant']),
            'metadata' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that this is a system prompt.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'system',
            'name' => 'System: ' . $this->faker->sentence(2),
        ]);
    }

    /**
     * Indicate that this is a user prompt.
     */
    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'user',
            'name' => 'User: ' . $this->faker->sentence(2),
        ]);
    }

    /**
     * Indicate that this is an assistant prompt.
     */
    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'assistant',
            'name' => 'Assistant: ' . $this->faker->sentence(2),
        ]);
    }

    /**
     * Add metadata to the template.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create template with an initial version.
     */
    public function withVersion(string $content = null, array $options = []): static
    {
        return $this->afterCreating(function (PromptTemplate $template) use ($content, $options) {
            $template->createVersion(
                $content ?? $this->generateDefaultContent($template->type),
                $options
            );
        });
    }

    /**
     * Create template with multiple versions.
     */
    public function withVersions(int $count = 3): static
    {
        return $this->afterCreating(function (PromptTemplate $template) use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $template->createVersion(
                    "Version {$i} content for {{ variable }}",
                    ['change_summary' => "Version {$i}"]
                );
            }
        });
    }

    /**
     * Create template with components.
     */
    public function withComponents(int $count = 2): static
    {
        return $this->afterCreating(function (PromptTemplate $template) use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $template->addComponent([
                    'key' => "component_{$i}",
                    'name' => "Component {$i}",
                    'content' => "This is component {$i} content.",
                    'position' => $i % 2 === 0 ? 'append' : 'prepend',
                    'order' => $i,
                    'is_default_enabled' => true,
                ]);
            }
        });
    }

    protected function generateDefaultContent(string $type): string
    {
        return match ($type) {
            'system' => "You are a helpful assistant.\n\nCurrent date: {{ current_date }}\n\nPlease assist the user with their request.",
            'user' => "User request: {{ user_input }}\n\nPlease provide a helpful response.",
            'assistant' => "Based on the previous context:\n\n{{ previous_result }}\n\nHere is my response:",
            default => "Template content with {{ variable }} placeholder.",
        };
    }
}
