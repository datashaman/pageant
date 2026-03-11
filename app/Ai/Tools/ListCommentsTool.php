<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListCommentsTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'List all comments on a GitHub issue or pull request.';
    }

    public function handle(Request $request): string
    {
        $comments = $this->github->listComments(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
        );

        return json_encode($comments, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The issue or pull request number.')
                ->required(),
        ];
    }
}
