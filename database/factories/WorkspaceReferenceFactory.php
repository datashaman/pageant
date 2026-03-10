<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceReference>
 */
class WorkspaceReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'source' => 'github',
            'source_reference' => fake()->userName().'/'.fake()->slug(2),
            'source_url' => fake()->optional()->url(),
        ];
    }

    public function issue(?int $number = null): static
    {
        $number ??= fake()->numberBetween(1, 999);

        return $this->state(fn (array $attributes) => [
            'source_reference' => $attributes['source_reference'].'#'.$number,
        ]);
    }
}
