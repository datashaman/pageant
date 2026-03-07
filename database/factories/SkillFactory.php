<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Skill>
 */
class SkillFactory extends Factory
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
            'argument_hint' => fake()->word(),
            'license' => fake()->randomElement(['MIT', 'Apache-2.0', 'GPL-3.0']),
            'enabled' => true,
            'path' => fake()->filePath(),
            'allowed_tools' => [],
            'provider' => fake()->randomElement(['anthropic', 'openai']),
            'model' => fake()->word(),
            'context' => fake()->sentence(),
            'source' => fake()->randomElement(['github', 'gitlab', 'bitbucket']),
            'source_reference' => fake()->slug(2),
            'source_url' => fake()->url(),
        ];
    }
}
