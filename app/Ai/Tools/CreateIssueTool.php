<?php

namespace App\Ai\Tools;

use App\Events\WorkItemCreated;
use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected ?GithubInstallation $installation = null,
        protected ?string $repoFullName = null,
    ) {}

    public function description(): string
    {
        return 'Create a new issue on a GitHub repository. Automatically creates a linked Pageant work item unless skip_work_item is true.';
    }

    public function handle(Request $request): string
    {
        $repoFullName = $this->repoFullName ?? $request['repo'];
        $installation = $this->installation;

        if (! $installation) {
            $repo = Repo::where('source', 'github')->where('source_reference', $repoFullName)->firstOrFail();
            $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();
        }

        $data = ['title' => $request['title']];

        foreach (['body', 'labels', 'assignees', 'milestone'] as $field) {
            if (isset($request[$field])) {
                $data[$field] = $request[$field];
            }
        }

        $issue = $this->github->createIssue($installation, $repoFullName, $data);

        $result = ['issue' => $issue];

        $skipWorkItem = isset($request['skip_work_item'])
            ? filter_var($request['skip_work_item'], FILTER_VALIDATE_BOOLEAN)
            : false;

        if (! $skipWorkItem) {
            try {
                $repo ??= Repo::where('source', 'github')->where('source_reference', $repoFullName)->firstOrFail();
                $workItem = $this->createWorkItem($repo, $installation, $repoFullName, $issue);
                $result['work_item'] = $workItem->toArray();
            } catch (\Throwable $e) {
                $result['work_item_error'] = 'Failed to create Pageant work item: '.$e->getMessage();
            }
        }

        return json_encode($result, JSON_PRETTY_PRINT);
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
                'description' => Str::limit($issue['body'] ?? '', 252),
                'source_url' => $issue['html_url'] ?? '',
            ]
        );

        if ($workItem->wasRecentlyCreated) {
            WorkItemCreated::dispatch($workItem, $repoFullName, $installation->installation_id);
        }

        return $workItem;
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
        ]);
    }
}
