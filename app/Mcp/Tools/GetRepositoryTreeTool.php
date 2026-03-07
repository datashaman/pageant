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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List files and directories in a repository tree. Use a branch name, tag, or commit SHA as the tree_sha parameter.')]
#[IsReadOnly]
#[IsOpenWorld]
class GetRepositoryTreeTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'tree_sha' => 'required|string',
            'recursive' => 'nullable|boolean',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $tree = $this->github->getRepositoryTree(
            $installation,
            $validated['repo'],
            $validated['tree_sha'],
            $validated['recursive'] ?? false,
        );

        return Response::text(json_encode($tree, JSON_PRETTY_PRINT));
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
            'tree_sha' => $schema->string()
                ->description('The SHA of the tree, or a branch/tag name (e.g. "main").')
                ->required(),
            'recursive' => $schema->boolean()
                ->description('If true, returns all files recursively. Defaults to false.'),
        ];
    }
}
