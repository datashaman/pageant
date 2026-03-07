<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GitHubPullRequestReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $pullRequest
     * @param  array<string, mixed>  $repository
     */
    public function __construct(
        public string $action,
        public array $pullRequest,
        public array $repository,
        public int $installationId,
    ) {}
}
