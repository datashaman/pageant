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

#[Description('Get the contents of a file from a GitHub repository. Returns the file content, SHA, and metadata.')]
#[IsReadOnly]
#[IsOpenWorld]
class GetFileContentsTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'path' => 'required|string',
            'ref' => 'nullable|string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $contents = $this->github->getFileContents(
            $installation,
            $validated['repo'],
            $validated['path'],
            $validated['ref'] ?? null,
        );

        if (isset($contents['content']) && isset($contents['encoding']) && $contents['encoding'] === 'base64') {
            $contents['decoded_content'] = base64_decode($contents['content']);
            unset($contents['content']);
        }

        return Response::text(json_encode($contents, JSON_PRETTY_PRINT));
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
            'ref' => $schema->string()
                ->description('Branch name, tag, or commit SHA. Defaults to the default branch.'),
        ];
    }
}
