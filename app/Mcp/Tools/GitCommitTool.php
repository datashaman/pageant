<?php

namespace App\Mcp\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Stage files and create a git commit.')]
#[IsOpenWorld]
class GitCommitTool extends Tool
{
    public function __construct(protected ExecutionDriver $driver) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'files' => 'nullable|array',
            'files.*' => 'string',
        ]);

        $files = $validated['files'] ?? null;

        if ($files) {
            $paths = array_map('escapeshellarg', $files);
            $addResult = $this->driver->exec('git add '.implode(' ', $paths));
        } else {
            $addResult = $this->driver->exec('git add -A');
        }

        if (! $addResult->isSuccessful()) {
            return Response::text(json_encode([
                'error' => 'Failed to stage files',
                'stderr' => $addResult->stderr,
            ], JSON_PRETTY_PRINT));
        }

        $message = escapeshellarg($validated['message']);
        $commitResult = $this->driver->exec("git commit -m {$message}");

        if (! $commitResult->isSuccessful()) {
            return Response::text(json_encode([
                'error' => 'Failed to commit',
                'stderr' => $commitResult->stderr,
            ], JSON_PRETTY_PRINT));
        }

        $hashResult = $this->driver->exec('git rev-parse --short HEAD');

        return Response::text(json_encode([
            'hash' => trim($hashResult->stdout),
            'summary' => trim($commitResult->stdout),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()
                ->description('The commit message.')
                ->required(),
            'files' => $schema->array()
                ->description('Specific file paths to stage. If omitted, all changes are staged.')
                ->items($schema->string()),
        ];
    }
}
