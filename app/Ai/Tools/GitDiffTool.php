<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GitDiffTool implements Tool
{
    public function __construct(protected ExecutionDriver $driver) {}

    public function description(): string
    {
        return 'Show changes in the working tree or between branches.';
    }

    public function handle(Request $request): string
    {
        $command = 'git diff';

        if ($request['staged'] ?? false) {
            $command .= ' --staged';
        }

        if ($request['branch'] ?? null) {
            $command .= ' '.escapeshellarg($request['branch']);
        }

        if ($request['path'] ?? null) {
            $command .= ' -- '.escapeshellarg($request['path']);
        }

        $result = $this->driver->exec($command);

        return json_encode([
            'diff' => $result->stdout,
            'empty' => trim($result->stdout) === '',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'staged' => $schema->boolean()
                ->description('Show only staged changes.'),
            'branch' => $schema->string()
                ->description('Compare against a specific branch.'),
            'path' => $schema->string()
                ->description('Limit diff to a specific file path.'),
        ];
    }
}
