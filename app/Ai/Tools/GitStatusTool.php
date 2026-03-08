<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GitStatusTool implements Tool
{
    public function __construct(protected ExecutionDriver $driver) {}

    public function description(): string
    {
        return 'Show working tree status (staged, unstaged, untracked files).';
    }

    public function handle(Request $request): string
    {
        $result = $this->driver->exec('git status --porcelain=v1');

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
