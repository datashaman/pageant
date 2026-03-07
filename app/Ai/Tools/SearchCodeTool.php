<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchCodeTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Search for code in a GitHub repository. Use GitHub search syntax, e.g. "className repo:owner/repo".';
    }

    public function handle(Request $request): string
    {
        $sanitized = preg_replace('/\b(repo|org):\S+/', '', $request['query']);
        $fullQuery = trim($sanitized).' repo:'.$this->repoFullName;

        $results = $this->github->searchCode($this->installation, $fullQuery);

        return json_encode($results, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query. Supports GitHub code search syntax (e.g. "function name", "extension:js").')
                ->required(),
        ];
    }
}
