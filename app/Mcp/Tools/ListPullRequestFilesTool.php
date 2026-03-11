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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List files changed in a pull request, with additions, deletions, and status for each file.')]
#[IsReadOnly]
#[IsOpenWorld]
class ListPullRequestFilesTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'pull_number' => 'required|integer|min:1',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $files = $this->github->listPullRequestFiles($installation, $validated['repo'], $validated['pull_number']);

        return Response::text(json_encode($files, JSON_PRETTY_PRINT));
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
        ];
    }
}
