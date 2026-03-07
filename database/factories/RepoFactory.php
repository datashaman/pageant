<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Repo>
 */
class RepoFactory extends Factory
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
            'name' => fake()->unique()->slug(3),
            'source' => fake()->randomElement(['github', 'gitlab', 'bitbucket']),
            'source_reference' => fake()->slug(2),
            'source_url' => fake()->url(),
        ];
    }
}
