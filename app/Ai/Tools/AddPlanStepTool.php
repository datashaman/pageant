<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddPlanStepTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Add a new step to an existing plan. The step is inserted at the specified position or appended at the end.';
    }

    public function handle(Request $request): string
    {
        $plan = Plan::forCurrentOrganization($this->user)
            ->findOrFail($request['plan_id']);

        if ($plan->isCompleted() || $plan->isCancelled()) {
            return json_encode([
                'error' => "Cannot add steps to a {$plan->status} plan.",
            ]);
        }

        $agent = Agent::forCurrentOrganization($this->user)
            ->findOrFail($request['agent_id']);

        $maxOrder = $plan->steps()->max('order') ?? 0;
        $order = $request['after_step'] ?? $maxOrder + 1;

        if (isset($request['after_step'])) {
            $plan->steps()
                ->where('order', '>', $request['after_step'])
                ->increment('order');

            $order = $request['after_step'] + 1;
        }

        $step = $plan->steps()->create([
            'agent_id' => $agent->id,
            'order' => $order,
            'status' => 'pending',
            'description' => $request['description'],
            'depends_on' => $request['depends_on'] ?? null,
        ]);

        return json_encode([
            'message' => "Step added to plan at position {$order}.",
            'step' => $step->load('agent')->toArray(),
            'plan' => $plan->load('steps.agent')->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->string()
                ->description('The UUID of the plan to add a step to.')
                ->required(),
            'agent_id' => $schema->string()
                ->description('The UUID of the agent to assign to this step.')
                ->required(),
            'description' => $schema->string()
                ->description('What this step should accomplish.')
                ->required(),
            'after_step' => $schema->integer()
                ->description('Insert after this step number (order). If omitted, appends at end.'),
            'depends_on' => $schema->array()
                ->items($schema->string())
                ->description('UUIDs of plan steps this step depends on.'),
        ];
    }
}
