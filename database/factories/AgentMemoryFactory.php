<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AgentMemory>
 */
class AgentMemoryFactory extends Factory
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
            'type' => fake()->randomElement(['learning', 'entity', 'pattern', 'failure']),
            'content' => fake()->paragraph(),
            'summary' => fake()->sentence(),
            'importance' => fake()->numberBetween(1, 10),
            'metadata' => null,
        ];
    }

    public function learning(): static
    {
        return $this->state(['type' => 'learning']);
    }

    public function entity(): static
    {
        return $this->state(['type' => 'entity']);
    }

    public function pattern(): static
    {
        return $this->state(['type' => 'pattern']);
    }

    public function failure(): static
    {
        return $this->state(['type' => 'failure']);
    }

    public function highImportance(): static
    {
        return $this->state(['importance' => fake()->numberBetween(8, 10)]);
    }

    public function lowImportance(): static
    {
        return $this->state(['importance' => fake()->numberBetween(1, 3)]);
    }
}
