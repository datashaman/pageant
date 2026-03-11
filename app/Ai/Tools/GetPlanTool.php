<?php

namespace App\Ai\Tools;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetPlanTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Get a plan with its steps and assigned agents.';
    }

    public function handle(Request $request): string
    {
        $plan = Plan::forCurrentOrganization($this->user)
            ->with('steps.agent', 'workspace')
            ->findOrFail($request['plan_id']);

        return json_encode($plan->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('The UUID of the plan.')
                ->required(),
        ];
    }
}
