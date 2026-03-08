<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreatePlanTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Create an execution plan for a work item, defining which agents run in what order.';
    }

    public function handle(Request $request): string
    {
        $organizationId = $this->user->current_organization_id;

        $workItem = WorkItem::where('organization_id', $organizationId)
            ->findOrFail($request['work_item_id']);

        $plan = Plan::create([
            'organization_id' => $organizationId,
            'work_item_id' => $workItem->id,
            'status' => 'pending',
            'summary' => $request['summary'],
        ]);

        foreach ($request['steps'] as $index => $stepData) {
            $agent = Agent::where('organization_id', $organizationId)
                ->findOrFail($stepData['agent_id']);

            $plan->steps()->create([
                'agent_id' => $agent->id,
                'order' => $index + 1,
                'status' => 'pending',
                'description' => $stepData['description'],
                'depends_on' => $stepData['depends_on'] ?? null,
            ]);
        }

        return json_encode(
            $plan->load('steps.agent')->toArray(),
            JSON_PRETTY_PRINT,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()
                ->description('The UUID of the work item this plan is for.')
                ->required(),
            'summary' => $schema->string()
                ->description('A summary of the overall approach for resolving this work item.')
                ->required(),
            'steps' => $schema->array()
                ->items($schema->object([
                    'agent_id' => $schema->string()
                        ->description('The UUID of the agent to run for this step.')
                        ->required(),
                    'description' => $schema->string()
                        ->description('What this step should accomplish.')
                        ->required(),
                    'depends_on' => $schema->array()
                        ->items($schema->string())
                        ->description('UUIDs of plan steps this step depends on (optional).'),
                ]))
                ->description('The ordered list of steps in the plan.')
                ->required(),
        ];
    }
}
