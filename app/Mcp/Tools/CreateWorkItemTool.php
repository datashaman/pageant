<?php

namespace App\Mcp\Tools;

use App\Events\WorkItemCreated;
use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create a work item from a GitHub issue, tracking it in Pageant.')]
#[IsOpenWorld]
class CreateWorkItemTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
            'board_id' => 'nullable|string',
            'project_id' => 'nullable|string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $issue = $this->github->getIssue($installation, $validated['repo'], $validated['issue_number']);

        $workItem = WorkItem::firstOrCreate(
            [
                'organization_id' => $repo->organization_id,
                'source' => 'github',
                'source_reference' => $validated['repo'].'#'.$validated['issue_number'],
            ],
            [
                'project_id' => $validated['project_id'] ?? null,
                'title' => $issue['title'],
                'description' => $issue['body'] ?? '',
                'board_id' => $validated['board_id'] ?? null,
                'source_url' => $issue['html_url'] ?? '',
            ]
        );

        if ($workItem->wasRecentlyCreated) {
            WorkItemCreated::dispatch($workItem, $validated['repo'], $installation->installation_id);
        }

        return Response::text(json_encode($workItem->toArray(), JSON_PRETTY_PRINT));
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
                ->description('The GitHub issue number to create a work item from.')
                ->required(),
            'board_id' => $schema->string()
                ->description('Optional board ID to place the work item on.'),
            'project_id' => $schema->string()
                ->description('Optional project ID to associate the work item with.'),
        ];
    }
}
