<?php

namespace App\Listeners;

use App\Concerns\DispatchesAgentsForEvent;
use App\Events\GitHubPullRequestReceived;

class HandleGitHubPullRequest
{
    use DispatchesAgentsForEvent;

    public function handle(GitHubPullRequestReceived $event): void
    {
        $repoFullName = $event->repository['full_name'];
        $pr = $event->pullRequest;

        $eventContext = implode("\n", [
            'Event: pull_request',
            "Action: {$event->action}",
            "Repository: {$repoFullName}",
            "PR #{$pr['number']}: {$pr['title']}",
            'Head: '.($pr['head']['ref'] ?? ''),
            'Base: '.($pr['base']['ref'] ?? ''),
            "Body:\n".($pr['body'] ?? '(empty)'),
        ]);

        $this->dispatchAgentsForRepo($repoFullName, $event->installationId, 'pull_request', $eventContext, $pr['number']);
    }
}
