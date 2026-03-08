<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileWorkItemStatuses;
use App\Models\WorkItem;
use Illuminate\Console\Command;

class ReconcileWorkItems extends Command
{
    protected $signature = 'work-items:reconcile';

    protected $description = 'Reconcile work item statuses with their linked GitHub issues';

    public function handle(): int
    {
        $hasGithubWorkItems = WorkItem::where('source', 'github')
            ->whereNotNull('source_reference')
            ->exists();

        if (! $hasGithubWorkItems) {
            $this->info('No GitHub-linked work items found.');

            return self::SUCCESS;
        }

        ReconcileWorkItemStatuses::dispatchSync();

        $this->info('Work item statuses reconciled.');

        return self::SUCCESS;
    }
}
