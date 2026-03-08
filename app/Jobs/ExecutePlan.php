<?php

namespace App\Jobs;

use App\Models\Plan;
use App\Services\WorkItemOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecutePlan implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Plan $plan,
    ) {}

    public function uniqueId(): string
    {
        return "execute-plan:{$this->plan->id}";
    }

    public function handle(WorkItemOrchestrator $orchestrator): void
    {
        $orchestrator->execute($this->plan);
    }
}
