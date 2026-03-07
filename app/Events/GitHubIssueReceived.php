<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GitHubIssueReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $issue
     * @param  array<string, mixed>  $repository
     * @param  array<string, mixed>|null  $label
     */
    public function __construct(
        public string $action,
        public array $issue,
        public array $repository,
        public int $installationId,
        public ?array $label = null,
    ) {}
}
