<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GitCommitTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Stage files and create a git commit.';
    }

    public function handle(Request $request): string
    {
        $files = $request['files'] ?? null;

        if ($files) {
            $paths = array_map('escapeshellarg', $files);
            $addResult = $this->driver->exec('git add -- '.implode(' ', $paths));
        } else {
            $addResult = $this->driver->exec('git add -A');
        }

        if (! $addResult->isSuccessful()) {
            return json_encode([
                'error' => 'Failed to stage files',
                'stderr' => $addResult->stderr,
            ], JSON_PRETTY_PRINT);
        }

        $message = escapeshellarg($request['message']);

        $authorArgs = '';
        if ($this->user) {
            $author = escapeshellarg("{$this->user->name} <{$this->user->email}>");
            $authorArgs = " --author={$author}";
        }

        $commitResult = $this->driver->exec("git commit -m {$message}{$authorArgs}");

        if (! $commitResult->isSuccessful()) {
            return json_encode([
                'error' => 'Failed to commit',
                'stderr' => $commitResult->stderr,
            ], JSON_PRETTY_PRINT);
        }

        $hashResult = $this->driver->exec('git rev-parse --short HEAD');

        if (! $hashResult->isSuccessful()) {
            return json_encode([
                'error' => trim($hashResult->stderr) ?: 'Failed to get commit hash',
                'exit_code' => $hashResult->exitCode,
            ], JSON_PRETTY_PRINT);
        }

        return json_encode([
            'hash' => trim($hashResult->stdout),
            'summary' => trim($commitResult->stdout),
        ], JSON_PRETTY_PRINT);
    }

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
