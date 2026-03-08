<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GitPushTool implements Tool
{
    public function __construct(protected ExecutionDriver $driver) {}

    public function description(): string
    {
        return 'Push commits to the remote repository.';
    }

    public function handle(Request $request): string
    {
        $branchResult = $this->driver->exec('git rev-parse --abbrev-ref HEAD');
        $branch = trim($branchResult->stdout);

        $command = 'git push';

        if ($request['force'] ?? false) {
            $command .= ' --force-with-lease';
        }

        $trackingResult = $this->driver->exec('git config branch.'.escapeshellarg($branch).'.remote');

        if (trim($trackingResult->stdout) === '') {
            $command .= ' -u origin '.escapeshellarg($branch);
        }

        $result = $this->driver->exec($command);

        if (! $result->isSuccessful()) {
            return json_encode([
                'error' => 'Failed to push',
                'stderr' => $result->stderr,
            ], JSON_PRETTY_PRINT);
        }

        return json_encode([
            'branch' => $branch,
            'output' => trim($result->stdout)."\n".trim($result->stderr),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'force' => $schema->boolean()
                ->description('Force push using --force-with-lease (default: false).'),
        ];
    }
}
