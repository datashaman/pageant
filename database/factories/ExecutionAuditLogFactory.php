<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExecutionAuditLog>
 */
class ExecutionAuditLogFactory extends Factory
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
            'work_item_id' => null,
            'agent_id' => fake()->uuid(),
            'type' => fake()->randomElement(['command', 'file_write', 'file_edit']),
            'detail' => fake()->sentence(),
            'exit_code' => fake()->optional()->numberBetween(0, 255),
        ];
    }

    public function command(string $command = 'echo hello', int $exitCode = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'command',
            'detail' => $command,
            'exit_code' => $exitCode,
        ]);
    }

    public function fileWrite(string $path = 'test.txt'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file_write',
            'detail' => $path,
            'exit_code' => null,
        ]);
    }

    public function fileEdit(string $path = 'test.txt'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file_edit',
            'detail' => $path,
            'exit_code' => null,
        ]);
    }
}
