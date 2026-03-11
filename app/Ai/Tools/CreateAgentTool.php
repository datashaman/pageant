<?php

namespace App\Ai\Tools;

use App\Ai\EventRegistry;
use App\Ai\ToolRegistry;
use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateAgentTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Create a new AI agent in Pageant, configured with tools and webhook events.';
    }

    public function handle(Request $request): string
    {
        $organizationId = $this->user->currentOrganizationId()
            ?? $this->user->organizations()->first()?->id;

        if (! $organizationId) {
            return json_encode(['error' => 'No organization found for the current user.']);
        }

        $data = [
            'organization_id' => $organizationId,
            'name' => $request['name'],
            'description' => $request['description'] ?? '',
            'tools' => $request['tools'] ?? [],
            'events' => $request['events'] ?? [],
            'provider' => $request['provider'] ?? 'anthropic',
            'model' => $request['model'] ?? 'inherit',
            'permission_mode' => $request['permission_mode'] ?? 'full',
            'max_turns' => $request['max_turns'] ?? 10,
            'background' => $request['background'] ?? false,
            'enabled' => $request['enabled'] ?? true,
        ];

        if (! empty($request['isolation'])) {
            $data['isolation'] = $request['isolation'];
        }

        $agent = Agent::create($data);

        $workspaceIds = $request['workspace_ids'] ?? [];

        if (! empty($workspaceIds)) {
            $validIds = Workspace::where('organization_id', $organizationId)
                ->whereIn('id', $workspaceIds)
                ->pluck('id')
                ->all();

            $agent->workspaces()->sync($validIds);
        }

        return json_encode($agent->load('workspaces')->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name of the agent.')
                ->required(),
            'description' => $schema->string()
                ->description('A description of what the agent does, used as its system instructions.'),
            'workspace_ids' => $schema->array()
                ->items($schema->string())
                ->description('Workspace IDs to attach the agent to.'),
            'tools' => $schema->array()
                ->description('Tool names the agent can use. Available: '.implode(', ', array_keys(ToolRegistry::available()))),
            'events' => $schema->array()
                ->description('Events the agent subscribes to. Available: '.implode(', ', array_keys(EventRegistry::available()))),
            'provider' => $schema->string()
                ->description('AI provider: "anthropic" or "openai". Defaults to "anthropic".'),
            'model' => $schema->string()
                ->description('Model name or "inherit" to use the provider default. Defaults to "inherit".'),
            'permission_mode' => $schema->string()
                ->description('Permission mode: "full" or "limited". Defaults to "full".'),
            'max_turns' => $schema->integer()
                ->description('Maximum number of turns the agent can take. Defaults to 10.'),
            'background' => $schema->boolean()
                ->description('Whether the agent runs in the background. Defaults to false.'),
            'isolation' => $schema->string()
                ->description('Isolation mode. Set to "worktree" to give the agent an isolated copy of the repository.'),
            'enabled' => $schema->boolean()
                ->description('Whether the agent is enabled. Defaults to true.'),
        ];
    }
}
