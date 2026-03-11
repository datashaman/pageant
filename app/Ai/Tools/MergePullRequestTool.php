<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class MergePullRequestTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Merge a pull request using merge, squash, or rebase strategy.';
    }

    public function handle(Request $request): string
    {
        $result = $this->github->mergePullRequest(
            $this->installation,
            $this->repoFullName,
            (int) $request['pull_number'],
            $request['commit_title'] ?? null,
            $request['merge_method'] ?? null,
            $this->user,
        );

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pull_number' => $schema->integer()
                ->description('The pull request number to merge.')
                ->required(),
            'commit_title' => $schema->string()
                ->description('Custom title for the merge commit.'),
            'merge_method' => $schema->string()
                ->enum(['merge', 'squash', 'rebase'])
                ->description('Merge strategy.'),
        ];
    }
}
