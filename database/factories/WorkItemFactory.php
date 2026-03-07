<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkItem>
 */
class WorkItemFactory extends Factory
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
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'board_id' => fake()->slug(2),
            'source' => fake()->randomElement(['github', 'gitlab', 'jira']),
            'source_reference' => fake()->slug(2),
            'source_url' => fake()->url(),
        ];
    }

    public function forProject(?Project $project = null): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project ?? Project::factory(),
        ]);
    }
}
