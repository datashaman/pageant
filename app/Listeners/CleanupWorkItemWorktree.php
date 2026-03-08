<?php

namespace App\Listeners;

use App\Events\WorkItemDeleted;
use App\Services\WorktreeManager;

class CleanupWorkItemWorktree
{
    public function __construct(
        protected WorktreeManager $worktreeManager,
    ) {}

    public function handle(WorkItemDeleted $event): void
    {
        $this->worktreeManager->cleanup($event->workItem);
    }
}
