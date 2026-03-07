<?php

namespace App\Ai\Tools;

use App\Events\WorkItemCreated;
use App\Models\GithubInstallation;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateWorkItemTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Create a work item from a GitHub issue, tracking it in Pageant.';
    }

    public function handle(Request $request): string
    {
        $issue = $this->github->getIssue(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
        );

        $repo = \App\Models\Repo::where('source', 'github')
            ->where('source_reference', $this->repoFullName)
            ->firstOrFail();

        $workItem = WorkItem::create([
            'organization_id' => $repo->organization_id,
            'project_id' => $request['project_id'] ?? null,
            'title' => $issue['title'],
            'description' => $issue['body'] ?? '',
            'board_id' => $request['board_id'],
            'source' => 'github',
            'source_reference' => $this->repoFullName.'#'.$request['issue_number'],
            'source_url' => $issue['html_url'] ?? '',
        ]);

        WorkItemCreated::dispatch($workItem, $this->repoFullName, $this->installation->installation_id);

        return json_encode($workItem->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The GitHub issue number to create a work item from.')
                ->required(),
            'board_id' => $schema->string()
                ->description('The board ID to place the work item on.')
                ->required(),
            'project_id' => $schema->string()
                ->description('Optional project ID to associate the work item with.'),
        ];
    }
}
