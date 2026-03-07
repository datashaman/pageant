<?php

namespace App\Mcp\Tools;

use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Remove a label from a GitHub issue.')]
#[IsIdempotent]
#[IsOpenWorld]
class RemoveLabelFromIssueTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
            'label' => 'required|string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $this->github->removeLabelFromIssue($installation, $validated['repo'], $validated['issue_number'], $validated['label']);

        return Response::text("Label '{$validated['label']}' removed from issue #{$validated['issue_number']}.");
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
                ->description('The issue number.')
                ->required(),
            'label' => $schema->string()
                ->description('The label name to remove.')
                ->required(),
        ];
    }
}
