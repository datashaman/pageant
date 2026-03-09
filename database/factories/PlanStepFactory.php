<?php

namespace Database\Factories;

use App\Enums\FailureCategory;
use App\Models\Agent;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanStep>
 */
class PlanStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'agent_id' => Agent::factory(),
            'order' => fake()->numberBetween(1, 10),
            'status' => 'pending',
            'description' => fake()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'result' => fake()->sentence(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'result' => 'Step failed: '.fake()->sentence(),
        ]);
    }

    public function failedWithCategory(FailureCategory $category, int $retryAttempts = 0): static
    {
        return $this->failed()->state(fn () => [
            'failure_category' => $category,
            'retry_attempts' => $retryAttempts,
        ]);
    }
}
