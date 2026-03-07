<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreatePullRequestReviewTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Submit a review on a pull request: approve, request changes, or comment.';
    }

    public function handle(Request $request): string
    {
        $review = $this->github->createPullRequestReview(
            $this->installation,
            $this->repoFullName,
            (int) $request['pull_number'],
            $request['event'],
            $request['body'] ?? null,
        );

        return json_encode($review, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pull_number' => $schema->integer()
                ->description('The pull request number.')
                ->required(),
            'event' => $schema->string()
                ->enum(['APPROVE', 'REQUEST_CHANGES', 'COMMENT'])
                ->description('The review action to perform.')
                ->required(),
            'body' => $schema->string()
                ->description('Review comment body. Required for REQUEST_CHANGES and COMMENT.'),
        ];
    }
}
