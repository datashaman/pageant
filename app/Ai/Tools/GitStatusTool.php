<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GitStatusTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Show working tree status (staged, unstaged, untracked files).';
    }

    public function handle(Request $request): string
    {
        $result = $this->driver->exec('git status --porcelain=v1');

        if (! $result->isSuccessful()) {
            return json_encode([
                'error' => trim($result->stderr) ?: 'Command failed',
                'exit_code' => $result->exitCode,
            ], JSON_PRETTY_PRINT);
        }

        return json_encode([
            'status' => $result->stdout,
            'clean' => trim($result->stdout) === '',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
