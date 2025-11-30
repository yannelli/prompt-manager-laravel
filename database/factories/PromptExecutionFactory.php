<?php

namespace Yannelli\PromptManager\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Yannelli\PromptManager\Models\PromptTemplateVersion;
use Yannelli\PromptManager\Models\PromptExecution;

class PromptExecutionFactory extends Factory
{
    protected $model = PromptExecution::class;

    public function definition(): array
    {
        return [
            'prompt_template_version_id' => PromptTemplateVersion::factory(),
            'input_variables' => [
                'variable' => $this->faker->word(),
            ],
            'enabled_components' => [],
            'rendered_output' => $this->faker->paragraph(),
            'pipeline_chain' => null,
            'execution_time_ms' => $this->faker->numberBetween(10, 500),
            'user_id' => null,
        ];
    }

    /**
     * Set user who executed the prompt.
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Set input variables.
     */
    public function withVariables(array $variables): static
    {
        return $this->state(fn (array $attributes) => [
            'input_variables' => $variables,
        ]);
    }

    /**
     * Set enabled components.
     */
    public function withComponents(array $components): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled_components' => $components,
        ]);
    }

    /**
     * Set pipeline chain info.
     */
    public function withPipelineChain(array $chain): static
    {
        return $this->state(fn (array $attributes) => [
            'pipeline_chain' => $chain,
        ]);
    }

    /**
     * Simulate a slow execution.
     */
    public function slow(int $ms = 2000): static
    {
        return $this->state(fn (array $attributes) => [
            'execution_time_ms' => $ms,
        ]);
    }

    /**
     * Simulate a fast execution.
     */
    public function fast(int $ms = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'execution_time_ms' => $ms,
        ]);
    }

    /**
     * Set specific output.
     */
    public function withOutput(string $output): static
    {
        return $this->state(fn (array $attributes) => [
            'rendered_output' => $output,
        ]);
    }
}
