<?php

namespace App\Events;

use App\Models\PlanStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanStepFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PlanStep $planStep,
    ) {}
}
