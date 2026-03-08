<?php

namespace App\Mcp\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Push commits to the remote repository.')]
class GitPushTool extends Tool
{
    public function __construct(protected ExecutionDriver $driver) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'force' => 'nullable|boolean',
        ]);

        $branchResult = $this->driver->exec('git rev-parse --abbrev-ref HEAD');
        $branch = trim($branchResult->stdout);

        $command = 'git push';

        if ($validated['force'] ?? false) {
            $command .= ' --force-with-lease';
        }

        $trackingResult = $this->driver->exec('git config branch.'.escapeshellarg($branch).'.remote');

        if (trim($trackingResult->stdout) === '') {
            $command .= ' -u origin '.escapeshellarg($branch);
        }

        $result = $this->driver->exec($command);

        if (! $result->isSuccessful()) {
            return Response::text(json_encode([
                'error' => 'Failed to push',
                'stderr' => $result->stderr,
            ], JSON_PRETTY_PRINT));
        }

        return Response::text(json_encode([
            'branch' => $branch,
            'output' => trim($result->stdout)."\n".trim($result->stderr),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'force' => $schema->boolean()
                ->description('Force push using --force-with-lease (default: false).'),
        ];
    }
}
