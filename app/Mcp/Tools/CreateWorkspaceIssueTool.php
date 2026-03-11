<?php

namespace App\Mcp\Tools;

use App\Models\GithubInstallation;
use App\Models\Workspace;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create a GitHub issue and add it as a workspace reference.')]
#[IsOpenWorld]
class CreateWorkspaceIssueTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace_id' => 'required|string',
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
        ]);

        $workspace = Workspace::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['workspace_id']);

        $ref = WorkspaceReference::where('source', 'github')
            ->where(function ($q) use ($validated) {
                $q->where('source_reference', $validated['repo'])
                    ->orWhere('source_reference', 'LIKE', $validated['repo'].'#%');
            })
            ->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $ref->workspace->organization_id)->firstOrFail();

        $issue = $this->github->getIssue($installation, $validated['repo'], $validated['issue_number']);

        $reference = $workspace->references()->firstOrCreate(
            [
                'source' => 'github',
                'source_reference' => $validated['repo'].'#'.$validated['issue_number'],
            ],
            [
                'source_url' => $issue['html_url'] ?? '',
            ]
        );

        return Response::text(json_encode($reference->toArray(), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('The workspace ID to add the issue reference to.')
                ->required(),
            'repo' => $schema->string()
                ->description('The repository in owner/repo format.')
                ->required(),
            'issue_number' => $schema->integer()
                ->description('The GitHub issue number to add as a workspace reference.')
                ->required(),
        ];
    }
}
