<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\Workspace;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReopenWorkspaceIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected ?GithubInstallation $installation = null,
        protected ?string $repoFullName = null,
    ) {}

    public function description(): string
    {
        return 'Reopen a closed GitHub issue referenced in a workspace.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::findOrFail($request['workspace_id']);

        $issueReference = $request['issue_reference'];

        if (! preg_match('/^(.+\/.+)#(\d+)$/', $issueReference, $matches)) {
            return json_encode(['error' => 'Invalid issue reference format. Expected owner/repo#number.']);
        }

        $repoFullName = $matches[1];
        $issueNumber = (int) $matches[2];

        $installation = $this->installation
            ?? GithubInstallation::where('organization_id', $workspace->organization_id)->firstOrFail();

        $issue = $this->github->updateIssue($installation, $repoFullName, $issueNumber, [
            'state' => 'open',
        ]);

        return json_encode([
            'message' => "Issue {$issueReference} reopened successfully.",
            'issue' => $issue,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
            'issue_reference' => $schema->string()
                ->description('The issue reference in owner/repo#number format.')
                ->required(),
        ];
    }
}
