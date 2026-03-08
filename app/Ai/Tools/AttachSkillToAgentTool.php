<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AttachSkillToAgentTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Attach a skill to an agent, giving the agent access to the skill\'s context and tools.';
    }

    public function handle(Request $request): string
    {
        $agent = Agent::where('organization_id', $this->user->current_organization_id)
            ->findOrFail($request['agent_id']);

        $skill = Skill::where('organization_id', $this->user->current_organization_id)
            ->findOrFail($request['skill_id']);

        $agent->skills()->syncWithoutDetaching([$skill->id]);

        return json_encode([
            'message' => "Skill '{$skill->name}' attached to agent '{$agent->name}'.",
            'agent' => $agent->load('skills')->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'agent_id' => $schema->string()
                ->description('The UUID of the agent.')
                ->required(),
            'skill_id' => $schema->string()
                ->description('The UUID of the skill to attach.')
                ->required(),
        ];
    }
}
