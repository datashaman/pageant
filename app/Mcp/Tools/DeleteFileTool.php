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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Delete a file from a GitHub repository by committing the deletion to a branch.')]
#[IsDestructive]
#[IsOpenWorld]
class DeleteFileTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'path' => 'required|string',
            'message' => 'required|string',
            'branch' => 'required|string',
            'sha' => 'required|string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $result = $this->github->deleteFile(
            $installation,
            $validated['repo'],
            $validated['path'],
            $validated['message'],
            $validated['branch'],
            $validated['sha'],
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
            'path' => $schema->string()
                ->description('The file path to delete.')
                ->required(),
            'message' => $schema->string()
                ->description('The commit message for the deletion.')
                ->required(),
            'branch' => $schema->string()
                ->description('The branch to commit the deletion to.')
                ->required(),
            'sha' => $schema->string()
                ->description('The SHA of the file to delete. Get it from GetFileContents.')
                ->required(),
        ];
    }
}
