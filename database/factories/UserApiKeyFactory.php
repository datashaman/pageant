<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserApiKey>
 */
class UserApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['anthropic', 'openai', 'gemini']),
            'api_key' => fake()->sha256(),
            'is_valid' => false,
        ];
    }

    public function valid(): static
    {
        return $this->state(fn () => [
            'is_valid' => true,
            'validated_at' => now(),
        ]);
    }
}
