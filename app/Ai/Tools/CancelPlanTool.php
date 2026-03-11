<?php

namespace App\Ai\Tools;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CancelPlanTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Cancel a pending or running plan.';
    }

    public function handle(Request $request): string
    {
        $plan = Plan::forCurrentOrganization($this->user)
            ->findOrFail($request['plan_id']);

        if ($plan->isCompleted() || $plan->isCancelled()) {
            return json_encode([
                'error' => "Plan cannot be cancelled (current status: {$plan->status}).",
            ]);
        }

        $plan->update(['status' => 'cancelled']);

        $plan->steps()
            ->whereIn('status', ['pending', 'running'])
            ->update(['status' => 'cancelled']);

        return json_encode([
            'message' => 'Plan cancelled.',
            'plan' => $plan->fresh()->load('steps')->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('The UUID of the plan to cancel.')
                ->required(),
        ];
    }
}
