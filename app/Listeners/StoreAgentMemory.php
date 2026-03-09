<?php

namespace App\Listeners;

use App\Events\PlanCompleted;
use App\Events\PlanFailed;
use App\Services\AgentMemoryService;

class StoreAgentMemory
{
    public function __construct(
        protected AgentMemoryService $memoryService,
    ) {}

    public function handle(PlanCompleted|PlanFailed $event): void
    {
        $this->memoryService->storeFromPlan($event->plan);
    }
}
