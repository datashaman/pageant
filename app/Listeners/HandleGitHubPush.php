<?php

namespace App\Listeners;

use App\Concerns\DispatchesAgentsForEvent;
use App\Events\GitHubPushReceived;

class HandleGitHubPush
{
    use DispatchesAgentsForEvent;

    public function handle(GitHubPushReceived $event): void
    {
        $repoFullName = $event->repository['full_name'];

        $commits = collect($event->commits)->map(fn (array $c) => sprintf(
            '- %s: %s',
            substr($c['id'] ?? '', 0, 7),
            $c['message'] ?? '',
        ))->implode("\n");

        $eventContext = implode("\n", [
            'Event: push',
            "Repository: {$repoFullName}",
            "Ref: {$event->ref}",
            "Before: {$event->before}",
            "After: {$event->after}",
            "Commits:\n{$commits}",
        ]);

        $this->dispatchAgentsForRepo($repoFullName, $event->installationId, 'push', $eventContext);
    }
}
