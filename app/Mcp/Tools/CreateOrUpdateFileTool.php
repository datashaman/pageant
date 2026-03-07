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

#[Description('Create or update a file in a GitHub repository by committing directly to a branch. To update an existing file, provide its current SHA.')]
#[IsOpenWorld]
class CreateOrUpdateFileTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'path' => 'required|string',
            'content' => 'required|string',
            'message' => 'required|string',
            'branch' => 'required|string',
            'sha' => 'nullable|string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $result = $this->github->createOrUpdateFile(
            $installation,
            $validated['repo'],
            $validated['path'],
            $validated['content'],
            $validated['message'],
            $validated['branch'],
            $validated['sha'] ?? null,
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
                ->description('The file path within the repository (e.g. "src/index.js").')
                ->required(),
            'content' => $schema->string()
                ->description('The new file content (plain text, will be base64-encoded automatically).')
                ->required(),
            'message' => $schema->string()
                ->description('The commit message.')
                ->required(),
            'branch' => $schema->string()
                ->description('The branch to commit to.')
                ->required(),
            'sha' => $schema->string()
                ->description('The SHA of the file being replaced. Required when updating an existing file. Get it from GetFileContents.'),
        ];
    }
}
