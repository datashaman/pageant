<?php

namespace App\Events;

use App\Models\Plan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Plan $plan,
    ) {}
}
