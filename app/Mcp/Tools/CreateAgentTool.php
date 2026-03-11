<?php

namespace App\Mcp\Tools;

use App\Ai\EventRegistry;
use App\Ai\ToolRegistry;
use App\Models\Agent;
use App\Models\Workspace;
use App\Models\WorkspaceReference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create a new AI agent in Pageant, configured with tools and webhook events.')]
#[IsOpenWorld]
class CreateAgentTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'nullable|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tools' => 'nullable|array',
            'tools.*' => 'string|in:'.implode(',', array_keys(ToolRegistry::available())),
            'events' => 'nullable|array',
            'provider' => 'nullable|string|in:anthropic,openai',
            'model' => 'nullable|string',
            'permission_mode' => 'nullable|string|in:full,limited',
            'max_turns' => 'nullable|integer|min:1',
            'background' => 'nullable|boolean',
            'isolation' => 'nullable|string|in:worktree',
            'enabled' => 'nullable|boolean',
            'workspace_ids' => 'nullable|array',
            'workspace_ids.*' => 'string',
        ]);

        $organizationId = null;

        if (! empty($validated['repo'])) {
            $ref = WorkspaceReference::where('source', 'github')
                ->whereHas('workspace', fn ($q) => $q->forCurrentOrganization())
                ->where(function ($q) use ($validated) {
                    $q->where('source_reference', $validated['repo'])
                        ->orWhere('source_reference', 'LIKE', $validated['repo'].'#%');
                })
                ->first();
            $organizationId = $ref?->workspace?->organization_id;
        }

        if (! $organizationId) {
            $user = auth()->user();

            if (! $user) {
                return Response::text(json_encode(['error' => 'Authentication required to create agents.']));
            }

            $organizationId = $user->currentOrganizationId()
                ?? $user->organizations()->first()?->id;
        }

        if (! $organizationId) {
            return Response::text(json_encode(['error' => 'No organization found. Provide a repo or ensure you belong to an organization.']));
        }

        $events = collect($validated['events'] ?? [])->map(function ($entry) {
            if (is_string($entry)) {
                return ['event' => $entry, 'filters' => []];
            }

            return $entry;
        })->values()->toArray();

        $data = [
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'tools' => $validated['tools'] ?? [],
            'events' => $events,
            'provider' => $validated['provider'] ?? 'anthropic',
            'model' => $validated['model'] ?? 'inherit',
            'permission_mode' => $validated['permission_mode'] ?? 'full',
            'max_turns' => $validated['max_turns'] ?? 10,
            'background' => $validated['background'] ?? false,
            'enabled' => $validated['enabled'] ?? true,
        ];

        if (! empty($validated['isolation'])) {
            $data['isolation'] = $validated['isolation'];
        }

        $agent = Agent::create($data);

        $workspaceIds = $validated['workspace_ids'] ?? [];

        if (! empty($workspaceIds)) {
            $validIds = Workspace::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $workspaceIds)
                ->pluck('id')
                ->toArray();

            $agent->workspaces()->sync($validIds);
        }

        return Response::text(json_encode($agent->load('workspaces')->toArray(), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema->string()
                ->description('A repository in owner/repo format to determine the organization. Optional — the agent will be created in the user\'s current organization if not provided.'),
            'name' => $schema->string()
                ->description('The name of the agent.')
                ->required(),
            'description' => $schema->string()
                ->description('A description of what the agent does, used as its system instructions.'),
            'tools' => $schema->array()
                ->description('Tool names the agent can use. Available: '.implode(', ', array_keys(ToolRegistry::available()))),
            'events' => $schema->array()
                ->description('Event subscriptions. Each entry is a string (e.g. "issues") or object {"event": "issues.opened", "filters": {"labels": ["bug"]}}. Available event keys: '.implode(', ', EventRegistry::allEventKeys())),
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
            'workspace_ids' => $schema->array()
                ->description('Workspace IDs to attach the agent to.'),
        ];
    }
}
