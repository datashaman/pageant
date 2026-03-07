<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GithubInstallation>
 */
class GithubInstallationFactory extends Factory
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
            'installation_id' => fake()->unique()->randomNumber(8),
            'account_login' => fake()->unique()->userName(),
            'account_type' => fake()->randomElement(['Organization', 'User']),
            'permissions' => ['issues' => 'write', 'pull_requests' => 'write'],
            'events' => ['issues', 'pull_request'],
        ];
    }
}
