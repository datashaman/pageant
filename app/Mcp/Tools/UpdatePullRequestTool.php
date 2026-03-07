<?php

namespace App\Mcp\Tools;

use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Update an existing pull request. Can modify title, body, state (open/closed), and base branch.')]
#[IsOpenWorld]
class UpdatePullRequestTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'pull_number' => 'required|integer|min:1',
            'title' => 'nullable|string',
            'body' => 'nullable|string',
            'state' => 'nullable|string|in:open,closed',
            'base' => 'nullable|string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $data = [];

        foreach (['title', 'body', 'state', 'base'] as $field) {
            if (isset($validated[$field])) {
                $data[$field] = $validated[$field];
            }
        }

        $pr = $this->github->updatePullRequest($installation, $validated['repo'], $validated['pull_number'], $data);

        return Response::text(json_encode($pr, JSON_PRETTY_PRINT));
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
            'pull_number' => $schema->integer()
                ->description('The pull request number to update.')
                ->required(),
            'title' => $schema->string()
                ->description('Updated pull request title.'),
            'body' => $schema->string()
                ->description('Updated pull request body/description in Markdown.'),
            'state' => $schema->string()
                ->enum(['open', 'closed'])
                ->description('Set pull request state. Use "closed" to close the PR.'),
            'base' => $schema->string()
                ->description('The branch to merge into (change the base branch).'),
        ];
    }
}
