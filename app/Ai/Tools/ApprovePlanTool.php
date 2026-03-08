<?php

namespace App\Ai\Tools;

use App\Jobs\ExecutePlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ApprovePlanTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Approve a pending plan, which queues it for execution.';
    }

    public function handle(Request $request): string
    {
        $plan = Plan::forCurrentOrganization($this->user)
            ->findOrFail($request['plan_id']);

        if (! $plan->isPending()) {
            return json_encode([
                'error' => "Plan is not pending (current status: {$plan->status}).",
            ]);
        }

        $plan->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        ExecutePlan::dispatch($plan);

        return json_encode([
            'message' => 'Plan approved and queued for execution.',
            'plan' => $plan->load('steps.agent')->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('The UUID of the plan to approve.')
                ->required(),
        ];
    }
}
