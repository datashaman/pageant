<?php

namespace App\Listeners;

use App\Concerns\DispatchesAgentsForEvent;
use App\Events\WorkItemCreated;

class HandleWorkItemCreated
{
    use DispatchesAgentsForEvent;

    public function handle(WorkItemCreated $event): void
    {
        $workItem = $event->workItem;
        $repoFullName = $event->repoFullName;

        $issueNumber = null;

        if (preg_match('/#(\d+)$/', $workItem->source_reference, $matches)) {
            $issueNumber = (int) $matches[1];
        }

        $eventContext = implode("\n", [
            'Event: work_item_created',
            "Repository: {$repoFullName}",
            "Work Item: {$workItem->title}",
            "Description: {$workItem->description}",
            "Source: {$workItem->source}",
            "Source Reference: {$workItem->source_reference}",
            "Source URL: {$workItem->source_url}",
        ]);

        $this->dispatchAgentsForRepo(
            $repoFullName,
            'work_item_created',
            null,
            [],
            $eventContext,
            $issueNumber,
        );
    }
}
