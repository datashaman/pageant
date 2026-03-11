<?php

namespace App\Mcp\Tools;

use App\Concerns\ResolvesGithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Update an existing GitHub issue. Can modify title, body, state, labels, assignees, and milestone.')]
#[IsOpenWorld]
class UpdateIssueTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
            'title' => 'nullable|string',
            'body' => 'nullable|string',
            'state' => 'nullable|string|in:open,closed',
            'state_reason' => 'nullable|string|in:completed,not_planned,reopened',
            'labels' => 'nullable|array',
            'labels.*' => 'string',
            'assignees' => 'nullable|array',
            'assignees.*' => 'string',
            'milestone' => 'nullable|integer',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $data = [];

        foreach (['title', 'body', 'state', 'state_reason', 'labels', 'assignees', 'milestone'] as $field) {
            if (isset($validated[$field])) {
                $data[$field] = $validated[$field];
            }
        }

        $issue = $this->github->updateIssue($installation, $validated['repo'], $validated['issue_number'], $data, auth()->user());

        return Response::text(json_encode($issue, JSON_PRETTY_PRINT));
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
                ->description('The issue number to update.')
                ->required(),
            'title' => $schema->string()
                ->description('Updated issue title.'),
            'body' => $schema->string()
                ->description('Updated issue body/description in Markdown.'),
            'state' => $schema->string()
                ->enum(['open', 'closed'])
                ->description('Set issue state to open or closed.'),
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
        ];
    }
}
