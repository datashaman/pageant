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

#[Description('Search for issues and pull requests in a GitHub repository. Use GitHub search syntax.')]
#[IsReadOnly]
#[IsOpenWorld]
class SearchIssuesTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'query' => 'required|string',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $fullQuery = $validated['query'].' repo:'.$validated['repo'];
        $results = $this->github->searchIssues($installation, $fullQuery);

        return Response::text(json_encode($results, JSON_PRETTY_PRINT));
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
            'query' => $schema->string()
                ->description('The search query. Supports GitHub search syntax (e.g. "is:open label:bug", "auth in:title").')
                ->required(),
        ];
    }
}
