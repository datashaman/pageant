<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchAgentsTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Search for agents that match requirements based on tools, skills, and description.';
    }

    public function handle(Request $request): string
    {
        $query = Agent::forCurrentOrganization($this->user)
            ->where('enabled', true)
            ->with('skills', 'workspaces');

        if (! empty($request['workspace_id'])) {
            $workspace = Workspace::forCurrentOrganization($this->user)
                ->findOrFail($request['workspace_id']);

            $query->whereHas('workspaces', function ($q) use ($workspace) {
                $q->where('workspaces.id', $workspace->id);
            });
        }

        if (! empty($request['tools'])) {
            $tools = $request['tools'];
            $query->where(function ($q) use ($tools) {
                foreach ($tools as $tool) {
                    $q->orWhereJsonContains('tools', $tool);
                }
            });
        }

        if (! empty($request['skills'])) {
            $skillNames = $request['skills'];
            $query->whereHas('skills', function ($q) use ($skillNames) {
                $q->where(function ($sq) use ($skillNames) {
                    foreach ($skillNames as $skillName) {
                        $sq->orWhere('name', 'like', "%{$skillName}%");
                    }
                });
            });
        }

        if (! empty($request['query'])) {
            $search = $request['query'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $agents = $query->get();

        return json_encode([
            'count' => $agents->count(),
            'agents' => $agents->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('The UUID of a workspace to find attached agents for.'),
            'tools' => $schema->array()
                ->items($schema->string())
                ->description('Tool names the agent should have.'),
            'skills' => $schema->array()
                ->items($schema->string())
                ->description('Skill names or keywords the agent should have.'),
            'query' => $schema->string()
                ->description('Free-text search query to match against agent name and description.'),
        ];
    }
}
