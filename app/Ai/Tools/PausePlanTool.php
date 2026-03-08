<?php

namespace App\Ai\Tools;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PausePlanTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Pause a running plan. The current step will complete, but no further steps will start.';
    }

    public function handle(Request $request): string
    {
        $plan = Plan::where('organization_id', $this->user->current_organization_id)
            ->findOrFail($request['plan_id']);

        if (! $plan->isRunning()) {
            return json_encode([
                'error' => "Plan is not running (current status: {$plan->status}).",
            ]);
        }

        $plan->update(['status' => 'paused']);

        return json_encode([
            'message' => 'Plan paused. Use resume_plan to continue execution.',
            'plan' => $plan->load('steps.agent')->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('The UUID of the plan to pause.')
                ->required(),
        ];
    }
}
