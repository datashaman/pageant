<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->word(),
            'enabled' => true,
            'description' => fake()->sentence(),
            'tools' => [],
            'events' => [],
            'provider' => fake()->randomElement(['anthropic', 'openai', 'gemini']),
            'model' => 'inherit',
            'secondary_model' => 'cheapest',
            'permission_mode' => fake()->randomElement(['full', 'limited']),
            'max_turns' => fake()->numberBetween(1, 100),
            'background' => false,
        ];
    }

    public function cheapest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'model' => 'cheapest',
        ]);
    }

    public function smartest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'model' => 'smartest',
        ]);
    }
}
