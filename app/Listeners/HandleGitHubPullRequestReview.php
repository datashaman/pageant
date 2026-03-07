<?php

namespace App\Listeners;

use App\Concerns\DispatchesAgentsForEvent;
use App\Events\GitHubPullRequestReviewReceived;

class HandleGitHubPullRequestReview
{
    use DispatchesAgentsForEvent;

    public function handle(GitHubPullRequestReviewReceived $event): void
    {
        $repoFullName = $event->repository['full_name'];
        $review = $event->review;
        $pr = $event->pullRequest;

        $eventContext = implode("\n", [
            'Event: pull_request_review',
            "Action: {$event->action}",
            "Repository: {$repoFullName}",
            "PR #{$pr['number']}: {$pr['title']}",
            'Reviewer: '.($review['user']['login'] ?? ''),
            'State: '.($review['state'] ?? ''),
            "Body:\n".($review['body'] ?? '(empty)'),
        ]);

        $labels = array_map(fn (array $l) => $l['name'], $pr['labels'] ?? []);

        $this->dispatchAgentsForRepo(
            $repoFullName,
            'pull_request_review',
            $event->action,
            [
                'labels' => $labels,
                'base_branch' => $pr['base']['ref'] ?? null,
            ],
            $eventContext,
            $pr['number'],
        );
    }
}
