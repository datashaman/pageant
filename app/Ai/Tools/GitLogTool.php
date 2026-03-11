<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GitLogTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'View commit history with structured output.';
    }

    public function handle(Request $request): string
    {
        $branch = $request['branch'] ?? null;

        if (isset($branch) && str_starts_with($branch, '-')) {
            return json_encode([
                'error' => 'Invalid branch parameter: must not start with -',
            ], JSON_PRETTY_PRINT);
        }

        $limit = $request['limit'] ?? 10;

        $command = 'git log --format=%H%n%h%n%an%n%ae%n%ai%n%s%n---';
        $command .= ' -n '.escapeshellarg((string) $limit);

        if ($branch) {
            $command .= ' '.escapeshellarg($branch);
        }

        if ($request['path'] ?? null) {
            $command .= ' -- '.escapeshellarg($request['path']);
        }

        $result = $this->driver->exec($command);

        if (! $result->isSuccessful()) {
            return json_encode([
                'error' => trim($result->stderr) ?: 'Command failed',
                'exit_code' => $result->exitCode,
            ], JSON_PRETTY_PRINT);
        }

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

        return json_encode([
            'commits' => $commits,
            'count' => count($commits),
        ], JSON_PRETTY_PRINT);
    }

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
