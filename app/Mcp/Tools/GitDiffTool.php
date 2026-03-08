<?php

namespace App\Mcp\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Show changes in the working tree or between branches.')]
#[IsReadOnly]
#[IsOpenWorld]
class GitDiffTool extends Tool
{
    public function __construct(protected ExecutionDriver $driver) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'staged' => 'nullable|boolean',
            'branch' => 'nullable|string',
            'path' => 'nullable|string',
        ]);

        $command = 'git diff';

        if ($validated['staged'] ?? false) {
            $command .= ' --staged';
        }

        if ($validated['branch'] ?? null) {
            $command .= ' '.escapeshellarg($validated['branch']);
        }

        if ($validated['path'] ?? null) {
            $command .= ' -- '.escapeshellarg($validated['path']);
        }

        $result = $this->driver->exec($command);

        return Response::text(json_encode([
            'diff' => $result->stdout,
            'empty' => trim($result->stdout) === '',
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
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
