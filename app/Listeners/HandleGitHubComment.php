<?php

namespace App\Listeners;

use App\Concerns\DispatchesAgentsForEvent;
use App\Events\GitHubCommentReceived;

class HandleGitHubComment
{
    use DispatchesAgentsForEvent;

    public function handle(GitHubCommentReceived $event): void
    {
        $repoFullName = $event->repository['full_name'];
        $comment = $event->comment;
        $issue = $event->issue;

        $issueType = isset($issue['pull_request']) ? 'PR' : 'Issue';

        $eventContext = implode("\n", [
            'Event: issue_comment',
            "Action: {$event->action}",
            "Repository: {$repoFullName}",
            "{$issueType} #{$issue['number']}: {$issue['title']}",
            'Commenter: '.($comment['user']['login'] ?? ''),
            "Comment:\n".($comment['body'] ?? ''),
        ]);

        $this->dispatchAgentsForRepo($repoFullName, $event->installationId, 'issue_comment', $eventContext, $issue['number']);
    }
}
