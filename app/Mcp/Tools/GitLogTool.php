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

#[Description('View commit history with structured output.')]
#[IsReadOnly]
#[IsOpenWorld]
class GitLogTool extends Tool
{
    public function __construct(protected ExecutionDriver $driver) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1',
            'branch' => 'nullable|string',
            'path' => 'nullable|string',
        ]);

        $limit = $validated['limit'] ?? 10;

        $command = 'git log --format=%H%n%h%n%an%n%ae%n%ai%n%s%n---';
        $command .= ' -n '.escapeshellarg((string) $limit);

        if ($validated['branch'] ?? null) {
            $command .= ' '.escapeshellarg($validated['branch']);
        }

        if ($validated['path'] ?? null) {
            $command .= ' -- '.escapeshellarg($validated['path']);
        }

        $result = $this->driver->exec($command);

        $commits = [];
        $entries = array_filter(explode("---\n", $result->stdout));

        foreach ($entries as $entry) {
            $lines = explode("\n", trim($entry));

            if (count($lines) >= 6) {
                $commits[] = [
                    'hash' => $lines[0],
                    'short_hash' => $lines[1],
                    'author' => $lines[2],
                    'email' => $lines[3],
                    'date' => $lines[4],
                    'subject' => $lines[5],
                ];
            }
        }

        return Response::text(json_encode([
            'commits' => $commits,
            'count' => count($commits),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Maximum number of commits to return (default: 10).'),
            'branch' => $schema->string()
                ->description('Show commits from a specific branch.'),
            'path' => $schema->string()
                ->description('Limit log to a specific file path.'),
        ];
    }
}
