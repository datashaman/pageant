<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetPullRequestDiffTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Get the unified diff for a pull request, showing all code changes with line numbers.';
    }

    public function handle(Request $request): string
    {
        return $this->github->getPullRequestDiff(
            $this->installation,
            $this->repoFullName,
            (int) $request['pull_number'],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pull_number' => $schema->integer()
                ->description('The pull request number.')
                ->required(),
        ];
    }
}
