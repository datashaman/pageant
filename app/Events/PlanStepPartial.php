<?php

namespace App\Events;

use App\Models\PlanStep;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanStepPartial implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PlanStep $planStep,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('organization.'.$this->planStep->plan->organization_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'plan_step_id' => $this->planStep->id,
            'plan_id' => $this->planStep->plan_id,
            'status' => $this->planStep->status,
            'order' => $this->planStep->order,
            'result' => $this->planStep->result,
            'progress_summary' => $this->planStep->progress_summary,
            'turns_used' => $this->planStep->turns_used,
            'completed_at' => $this->planStep->completed_at?->toISOString(),
        ];
    }
}
