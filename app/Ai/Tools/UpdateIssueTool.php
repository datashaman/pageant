<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected ?GithubInstallation $installation = null,
        protected ?string $repoFullName = null,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Update an existing GitHub issue. Can modify title, body, state, labels, assignees, and milestone.';
    }

    public function handle(Request $request): string
    {
        $repoFullName = $this->repoFullName ?? $request['repo'];
        $installation = $this->installation;

        if (! $installation) {
            $reference = WorkspaceReference::where('source', 'github')
                ->where(function ($q) use ($repoFullName) {
                    $q->where('source_reference', $repoFullName)
                        ->orWhere('source_reference', 'LIKE', $repoFullName.'#%');
                })
                ->firstOrFail();

            $installation = GithubInstallation::where('organization_id', $reference->workspace->organization_id)->firstOrFail();
        }

        $data = [];

        foreach (['title', 'body', 'state', 'state_reason', 'labels', 'assignees', 'milestone'] as $field) {
            if (isset($request[$field])) {
                $data[$field] = $request[$field];
            }
        }

        $issue = $this->github->updateIssue(
            $installation,
            $repoFullName,
            (int) $request['issue_number'],
            $data,
            $this->user,
        );

        return json_encode($issue, JSON_PRETTY_PRINT);
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
                ->description('The issue number to update.')
                ->required(),
            'title' => $schema->string()
                ->description('Updated issue title.'),
            'body' => $schema->string()
                ->description('Updated issue body/description in Markdown.'),
            'state' => $schema->string()
                ->enum(['open', 'closed'])
                ->description('Set issue state.'),
            'state_reason' => $schema->string()
                ->enum(['completed', 'not_planned', 'reopened'])
                ->description('Reason for the state change.'),
            'labels' => $schema->array()
                ->items($schema->string())
                ->description('Replace all labels on the issue with these.'),
            'assignees' => $schema->array()
                ->items($schema->string())
                ->description('Replace all assignees with these GitHub usernames.'),
            'milestone' => $schema->integer()
                ->description('Milestone number to associate with the issue.'),
        ]);
    }
}
