<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GitHubCommentReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $comment
     * @param  array<string, mixed>  $issue
     * @param  array<string, mixed>  $repository
     */
    public function __construct(
        public string $action,
        public array $comment,
        public array $issue,
        public array $repository,
        public int $installationId,
    ) {}
}
