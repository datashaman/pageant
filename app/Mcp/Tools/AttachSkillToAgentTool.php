<?php

namespace App\Mcp\Tools;

use App\Models\Agent;
use App\Models\Skill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Attach a skill to an agent, giving the agent access to the skill\'s context and tools.')]
#[IsOpenWorld]
class AttachSkillToAgentTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'agent_id' => 'required|string',
            'skill_id' => 'required|string',
        ]);

        $agent = Agent::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['agent_id']);

        $skill = Skill::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['skill_id']);

        $agent->skills()->syncWithoutDetaching([$skill->id]);

        return Response::text(json_encode([
            'message' => "Skill '{$skill->name}' attached to agent '{$agent->name}'.",
            'agent' => $agent->load('skills')->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
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
