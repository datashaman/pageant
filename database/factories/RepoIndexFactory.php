<?php

namespace Database\Factories;

use App\Models\Repo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RepoIndex>
 */
class RepoIndexFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repo_id' => Repo::factory(),
            'commit_hash' => fake()->sha1(),
            'structural_map' => "# Structural Map\n\n## src/Example.php\n- class Example\n  - method doSomething(string \$input): bool",
            'token_count' => fake()->numberBetween(100, 1000),
        ];
    }
}
