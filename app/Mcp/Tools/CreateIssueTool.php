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

#[Description('Create a new issue on a GitHub repository. Automatically creates a linked Pageant work item unless skip_work_item is true.')]
#[IsOpenWorld]
class CreateIssueTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'title' => 'required|string',
            'body' => 'nullable|string',
            'labels' => 'nullable|array',
            'labels.*' => 'string',
            'assignees' => 'nullable|array',
            'assignees.*' => 'string',
            'milestone' => 'nullable|integer',
            'skip_work_item' => 'nullable|boolean',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $data = ['title' => $validated['title']];

        foreach (['body', 'labels', 'assignees', 'milestone'] as $field) {
            if (isset($validated[$field])) {
                $data[$field] = $validated[$field];
            }
        }

        $issue = $this->github->createIssue($installation, $validated['repo'], $data);

        $result = ['issue' => $issue];

        $skipWorkItem = isset($validated['skip_work_item'])
            ? filter_var($validated['skip_work_item'], FILTER_VALIDATE_BOOLEAN)
            : false;

        if (! $skipWorkItem) {
            try {
                $workItem = $this->createWorkItem($repo, $installation, $validated['repo'], $issue);
                $result['work_item'] = $workItem->toArray();
            } catch (\Throwable $e) {
                $result['work_item_error'] = 'Failed to create Pageant work item: '.$e->getMessage();
            }
        }

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }

    protected function createWorkItem(Repo $repo, GithubInstallation $installation, string $repoFullName, array $issue): WorkItem
    {
        $workItem = WorkItem::firstOrCreate(
            [
                'organization_id' => $repo->organization_id,
                'source' => 'github',
                'source_reference' => $repoFullName.'#'.$issue['number'],
            ],
            [
                'project_id' => $repo->inferProjectId(),
                'title' => $issue['title'],
                'description' => $issue['body'] ?? '',
                'source_url' => $issue['html_url'] ?? '',
            ]
        );

        if ($workItem->wasRecentlyCreated) {
            WorkItemCreated::dispatch($workItem, $repoFullName, $installation->installation_id);
        }

        return $workItem;
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
            'title' => $schema->string()
                ->description('The issue title.')
                ->required(),
            'body' => $schema->string()
                ->description('The issue body/description in Markdown.'),
            'labels' => $schema->array()
                ->items($schema->string())
                ->description('Label names to apply to the issue.'),
            'assignees' => $schema->array()
                ->items($schema->string())
                ->description('GitHub usernames to assign to the issue.'),
            'milestone' => $schema->integer()
                ->description('Milestone number to associate with the issue.'),
            'skip_work_item' => $schema->boolean()
                ->description('Set to true to skip automatic Pageant work item creation. Defaults to false.'),
        ];
    }
}
