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
        return 'Resume a paused plan, continuing from the next pending step.';
    }

    public function handle(Request $request): string
    {
        $plan = Plan::where('organization_id', $this->user->current_organization_id)
            ->findOrFail($request['plan_id']);

        if ($plan->status !== 'paused') {
            return json_encode([
                'error' => "Plan is not paused (current status: {$plan->status}).",
            ]);
        }

        $plan->update(['status' => 'approved']);

        ExecutePlan::dispatch($plan);

        return json_encode([
            'message' => 'Plan resumed and queued for execution.',
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
