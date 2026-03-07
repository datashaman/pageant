<?php

namespace App\Listeners;

use App\Concerns\DispatchesAgentsForEvent;
use App\Events\GitHubIssueReceived;

class HandleGitHubIssue
{
    use DispatchesAgentsForEvent;

    public function handle(GitHubIssueReceived $event): void
    {
        $repoFullName = $event->repository['full_name'];
        $issue = $event->issue;

        $lines = [
            'Event: issues',
            "Action: {$event->action}",
            "Repository: {$repoFullName}",
            "Issue #{$issue['number']}: {$issue['title']}",
            'Author: '.($issue['user']['login'] ?? ''),
            "Body:\n".($issue['body'] ?? '(empty)'),
        ];

        if ($event->label) {
            $lines[] = "Label: {$event->label['name']}";
        }

        $eventContext = implode("\n", $lines);

        $labels = array_map(fn (array $l) => $l['name'], $issue['labels'] ?? []);

        $this->dispatchAgentsForRepo(
            $repoFullName,
            'issues',
            $event->action,
            ['labels' => $labels],
            $eventContext,
            $issue['number'],
        );
    }
}
