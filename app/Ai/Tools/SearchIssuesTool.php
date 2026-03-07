<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchIssuesTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Search for issues and pull requests in a GitHub repository. Use GitHub search syntax.';
    }

    public function handle(Request $request): string
    {
        $sanitized = preg_replace('/\b(repo|org):\S+/', '', $request['query']);
        $fullQuery = trim($sanitized).' repo:'.$this->repoFullName;

        $results = $this->github->searchIssues($this->installation, $fullQuery);

        return json_encode($results, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query. Supports GitHub search syntax (e.g. "is:open label:bug", "auth in:title").')
                ->required(),
        ];
    }
}
