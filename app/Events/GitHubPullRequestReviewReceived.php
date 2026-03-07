<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GitHubPullRequestReviewReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>  $pullRequest
     * @param  array<string, mixed>  $repository
     */
    public function __construct(
        public string $action,
        public array $review,
        public array $pullRequest,
        public array $repository,
        public int $installationId,
    ) {}
}
