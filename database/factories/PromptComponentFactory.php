<?php

namespace Yannelli\PromptManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Yannelli\PromptManager\Models\PromptTemplate;
use Yannelli\PromptManager\Models\PromptComponent;

class PromptComponentFactory extends Factory
{
    protected $model = PromptComponent::class;

    public function definition(): array
    {
        return [
            'prompt_template_id' => PromptTemplate::factory(),
            'key' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(3, true),
            'content' => $this->faker->paragraph(),
            'position' => $this->faker->randomElement(['prepend', 'append']),
            'order' => $this->faker->numberBetween(0, 10),
            'is_default_enabled' => true,
            'conditions' => null,
        ];
    }

    /**
     * Indicate that the component is disabled by default.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default_enabled' => false,
        ]);
    }

    /**
     * Set position to prepend.
     */
    public function prepend(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'prepend',
        ]);
    }

    /**
     * Set position to append.
     */
    public function append(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'append',
        ]);
    }

    /**
     * Set position to replace a marker.
     */
    public function replaceMarker(string $marker): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => "replace:{$marker}",
        ]);
    }

    /**
     * Add conditional display rules.
     */
    public function withConditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => $conditions,
        ]);
    }

    /**
     * Create a component that shows when a variable equals a value.
     */
    public function whenVariableEquals(string $field, mixed $value): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => [
                ['field' => $field, 'operator' => '=', 'value' => $value],
            ],
        ]);
    }

    /**
     * Create a component that shows when a variable is in a list.
     */
    public function whenVariableIn(string $field, array $values): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => [
                ['field' => $field, 'operator' => 'in', 'value' => $values],
            ],
        ]);
    }

    /**
     * Set specific order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }

    /**
     * Set specific content.
     */
    public function withContent(string $content): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $content,
        ]);
    }
}
