<?php

namespace App\Ai\Tools;

use App\Events\WorkItemCreated;
use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateWorkItemTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected ?GithubInstallation $installation = null,
        protected ?string $repoFullName = null,
    ) {}

    public function description(): string
    {
        return 'Create a work item from a GitHub issue, tracking it in Pageant.';
    }

    public function handle(Request $request): string
    {
        $repoFullName = $this->repoFullName ?? $request['repo'];

        $repo = Repo::where('source', 'github')->where('source_reference', $repoFullName)->firstOrFail();
        $installation = $this->installation
            ?? GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $issue = $this->github->getIssue(
            $installation,
            $repoFullName,
            (int) $request['issue_number'],
        );

        $projectId = filled($request['project_id'] ?? null)
            ? $request['project_id']
            : $repo->inferProjectId();

        $workItem = WorkItem::firstOrCreate(
            [
                'organization_id' => $repo->organization_id,
                'source' => 'github',
                'source_reference' => $repoFullName.'#'.$request['issue_number'],
            ],
            [
                'project_id' => $projectId,
                'title' => $issue['title'],
                'description' => $issue['body'] ?? '',
                'board_id' => $request['board_id'] ?? null,
                'source_url' => $issue['html_url'] ?? '',
            ]
        );

        if ($workItem->wasRecentlyCreated) {
            WorkItemCreated::dispatch($workItem, $repoFullName, $installation->installation_id);
        }

        return json_encode($workItem->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = [];

        if (! $this->repoFullName) {
            $fields['repo'] = $schema->string()
                ->description('The repository in owner/repo format.')
                ->required();
        }

        return array_merge($fields, [
            'issue_number' => $schema->integer()
                ->description('The GitHub issue number to create a work item from.')
                ->required(),
            'board_id' => $schema->string()
                ->description('Optional board ID to place the work item on.'),
            'project_id' => $schema->string()
                ->description('Optional project ID to associate the work item with.'),
        ]);
    }
}
