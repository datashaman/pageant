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

#[Description('Request reviewers for a pull request by GitHub username or team slug.')]
#[IsOpenWorld]
class RequestReviewersTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'pull_number' => 'required|integer|min:1',
            'reviewers' => 'nullable|array',
            'reviewers.*' => 'string',
            'team_reviewers' => 'nullable|array',
            'team_reviewers.*' => 'string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $result = $this->github->requestReviewers(
            $installation,
            $validated['repo'],
            $validated['pull_number'],
            $validated['reviewers'] ?? [],
            $validated['team_reviewers'] ?? [],
        );

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
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
                ->description('The pull request number.')
                ->required(),
            'reviewers' => $schema->array()
                ->items($schema->string())
                ->description('GitHub usernames to request review from.'),
            'team_reviewers' => $schema->array()
                ->items($schema->string())
                ->description('Team slugs to request review from.'),
        ];
    }
}
