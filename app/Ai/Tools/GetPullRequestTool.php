<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetPullRequestTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Get a single pull request by number, including title, body, state, head/base branches, mergeable status, and diff stats.';
    }

    public function handle(Request $request): string
    {
        $pr = $this->github->getPullRequest(
            $this->installation,
            $this->repoFullName,
            (int) $request['pull_number'],
        );

        return json_encode($pr, JSON_PRETTY_PRINT);
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
