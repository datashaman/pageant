<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
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
        return 'Create an execution plan for a workspace, defining which agents run in what order.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::forCurrentOrganization($this->user)
            ->findOrFail($request['workspace_id']);

        $plan = Plan::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'status' => 'pending',
            'summary' => $request['summary'],
            'created_by' => $this->user->id,
        ]);

        foreach ($request['steps'] as $index => $stepData) {
            $agent = Agent::forCurrentOrganization($this->user)
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
            'workspace_id' => $schema->string()
                ->description('The UUID of the workspace this plan is for.')
                ->required(),
            'summary' => $schema->string()
                ->description('A summary of the overall approach for this plan.')
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
