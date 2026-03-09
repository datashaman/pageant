<?php

namespace App\Ai\Tools;

use App\Jobs\ExecutePlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ResumePlanTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Resume a paused or failed plan, continuing from the first pending/failed step.';
    }

    public function handle(Request $request): string
    {
        $plan = Plan::forCurrentOrganization($this->user)
            ->findOrFail($request['plan_id']);

        if (! $plan->isResumable()) {
            return json_encode([
                'error' => "Plan is not resumable (current status: {$plan->status}). Only paused or failed plans can be resumed.",
            ]);
        }

        $plan->resetForResume();

        $plan->update([
            'status' => 'approved',
            'completed_at' => null,
        ]);

        ExecutePlan::dispatch($plan);

        return json_encode([
            'message' => 'Plan resumed and queued for execution. Completed steps will be skipped.',
            'plan' => $plan->load('steps.agent')->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('The UUID of the plan to resume.')
                ->required(),
        ];
    }
}
