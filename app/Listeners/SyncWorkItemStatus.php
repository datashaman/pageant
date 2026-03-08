<?php

namespace App\Listeners;

use App\Events\GitHubIssueReceived;
use App\Models\GithubInstallation;
use App\Models\WorkItem;

class SyncWorkItemStatus
{
    public function handle(GitHubIssueReceived $event): void
    {
        $installation = GithubInstallation::where('installation_id', $event->installationId)->first();

        if (! $installation) {
            return;
        }

        $repoFullName = $event->repository['full_name'];
        $issueNumber = $event->issue['number'];
        $sourceReference = "{$repoFullName}#{$issueNumber}";

        $workItem = WorkItem::where('organization_id', $installation->organization_id)
            ->where('source', 'github')
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
