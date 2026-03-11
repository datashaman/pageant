<?php

namespace App\Mcp\Tools;

use App\Models\GithubInstallation;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Reopen a GitHub issue that is tracked as a workspace reference.')]
#[IsOpenWorld]
class ReopenWorkspaceIssueTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
        ]);

        $sourceReference = $validated['repo'].'#'.$validated['issue_number'];

        $ref = WorkspaceReference::where('source', 'github')
            ->where('source_reference', $sourceReference)
            ->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $ref->workspace->organization_id)->firstOrFail();

        $issue = $this->github->updateIssue($installation, $validated['repo'], $validated['issue_number'], [
            'state' => 'open',
            'state_reason' => 'reopened',
        ], auth()->user());

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
                ->description('The GitHub issue number to reopen.')
                ->required(),
        ];
    }
}
