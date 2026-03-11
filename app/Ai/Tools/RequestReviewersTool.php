<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RequestReviewersTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Request reviewers for a pull request by GitHub username or team slug.';
    }

    public function handle(Request $request): string
    {
        $result = $this->github->requestReviewers(
            $this->installation,
            $this->repoFullName,
            (int) $request['pull_number'],
            $request['reviewers'] ?? [],
            $request['team_reviewers'] ?? [],
            $this->user,
        );

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pull_number' => $schema->integer()
                ->description('The pull request number.')
                ->required(),
            'reviewers' => $schema->array()
                ->items($schema->string())
                ->description('GitHub usernames to request review from.'),
            'team_reviewers' => $schema->array()
                ->items($schema->string())
                ->description('Team slugs to request review from.'),
        ];
    }
}
