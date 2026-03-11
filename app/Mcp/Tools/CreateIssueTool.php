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

#[Description('Create a new issue on a GitHub repository.')]
#[IsOpenWorld]
class CreateIssueTool extends Tool
{
    use ResolvesGithubInstallation;

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
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $data = ['title' => $validated['title']];

        foreach (['body', 'labels', 'assignees', 'milestone'] as $field) {
            if (isset($validated[$field])) {
                $data[$field] = $validated[$field];
            }
        }

        $issue = $this->github->createIssue($installation, $validated['repo'], $data, auth()->user());

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
        ];
    }
}
