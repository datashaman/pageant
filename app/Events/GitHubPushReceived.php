<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GitHubPushReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $commits
     * @param  array<string, mixed>  $repository
     */
    public function __construct(
        public string $ref,
        public ?string $before,
        public ?string $after,
        public array $commits,
        public array $repository,
        public int $installationId,
    ) {}
}
