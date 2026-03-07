<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListPullRequestsTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'List pull requests on a GitHub repository, optionally filtered by state.';
    }

    public function handle(Request $request): string
    {
        $state = $request['state'] ?? 'open';
        $prs = $this->github->listPullRequests($this->installation, $this->repoFullName, $state);

        return json_encode($prs, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()
                ->enum(['open', 'closed', 'all'])
                ->description('Filter by state. Defaults to "open".'),
        ];
    }
}
