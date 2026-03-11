<?php

namespace App\Mcp\Tools;

use App\Concerns\ResolvesGithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Close a GitHub issue with an optional reason (completed or not_planned).')]
#[IsOpenWorld]
class CloseIssueTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
            'state_reason' => 'nullable|string|in:completed,not_planned',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $data = [
            'state' => 'closed',
            'state_reason' => $validated['state_reason'] ?? 'completed',
        ];

        $issue = $this->github->updateIssue($installation, $validated['repo'], $validated['issue_number'], $data, auth()->user());

        return Response::text(json_encode($issue, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema->string()
                ->description('The repository in owner/repo format.')
                ->required(),
            'issue_number' => $schema->integer()
                ->description('The issue number to close.')
                ->required(),
            'state_reason' => $schema->string()
                ->enum(['completed', 'not_planned'])
                ->description('Reason for closing. Defaults to "completed".'),
        ];
    }
}
