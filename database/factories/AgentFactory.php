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
            'description' => fake()->sentence(),
            'tools' => [],
            'disallowed_tools' => [],
            'provider' => fake()->randomElement(['anthropic', 'openai']),
            'model' => 'inherit',
            'permission_mode' => fake()->randomElement(['full', 'limited']),
            'max_turns' => fake()->numberBetween(1, 100),
            'background' => false,
            'isolation' => 'false',
        ];
    }
}
