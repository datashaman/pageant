<?php

namespace App\Listeners;

use App\Events\GitHubIssueReceived;
use App\Models\WorkItem;

class SyncWorkItemStatus
{
    public function handle(GitHubIssueReceived $event): void
    {
        $repoFullName = $event->repository['full_name'];
        $issueNumber = $event->issue['number'];
        $sourceReference = "{$repoFullName}#{$issueNumber}";

        $workItem = WorkItem::where('source', 'github')
            ->where('source_reference', $sourceReference)
            ->first();

        if (! $workItem) {
            return;
        }

        $status = match ($event->action) {
            'closed' => 'closed',
            'reopened' => 'open',
            default => null,
        };

        if ($status && $workItem->status !== $status) {
            $workItem->update(['status' => $status]);
        }
    }
}
