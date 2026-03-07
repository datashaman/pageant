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

#[Description('Merge a pull request using merge, squash, or rebase strategy.')]
#[IsOpenWorld]
class MergePullRequestTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'pull_number' => 'required|integer|min:1',
            'commit_title' => 'nullable|string',
            'merge_method' => 'nullable|string|in:merge,squash,rebase',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $result = $this->github->mergePullRequest(
            $installation,
            $validated['repo'],
            $validated['pull_number'],
            $validated['commit_title'] ?? null,
            $validated['merge_method'] ?? null,
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
                ->description('The pull request number to merge.')
                ->required(),
            'commit_title' => $schema->string()
                ->description('Custom title for the merge commit.'),
            'merge_method' => $schema->string()
                ->enum(['merge', 'squash', 'rebase'])
                ->description('Merge strategy. Defaults to the repository setting.'),
        ];
    }
}
