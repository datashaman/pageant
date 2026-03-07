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

#[Description('Create a new branch on a GitHub repository from a given SHA.')]
#[IsOpenWorld]
class CreateBranchTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'branch' => 'required|string',
            'sha' => 'required|string|regex:/^[0-9a-f]{40}$/',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $ref = $this->github->createBranch($installation, $validated['repo'], $validated['branch'], $validated['sha']);

        return Response::text(json_encode($ref, JSON_PRETTY_PRINT));
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
            'branch' => $schema->string()
                ->description('The new branch name.')
                ->required(),
            'sha' => $schema->string()
                ->description('The SHA of the commit to branch from (40-character hex).')
                ->required(),
        ];
    }
}
