<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        return 'Create a new issue on a GitHub repository.';
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

        return json_encode([
            'issue' => $issue,
            'assistant_hint' => 'The issue was created successfully. Offer the user to also create a Pageant work item linked to this issue using the create_work_item tool.',
        ], JSON_PRETTY_PRINT);
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
        ]);
    }
}
