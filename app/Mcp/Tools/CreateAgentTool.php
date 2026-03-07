<?php

namespace App\Mcp\Tools;

use App\Ai\EventRegistry;
use App\Ai\ToolRegistry;
use App\Models\Agent;
use App\Models\Repo;
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
            'repo' => 'required|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tools' => 'nullable|array',
            'tools.*' => 'string|in:'.implode(',', array_keys(ToolRegistry::available())),
            'events' => 'nullable|array',
            'events.*' => 'string|in:'.implode(',', array_keys(EventRegistry::available())),
            'provider' => 'nullable|string|in:anthropic,openai',
            'model' => 'nullable|string',
            'permission_mode' => 'nullable|string|in:full,limited',
            'max_turns' => 'nullable|integer|min:1',
            'background' => 'nullable|boolean',
            'isolation' => 'nullable|string|in:worktree',
            'enabled' => 'nullable|boolean',
            'repo_names' => 'nullable|array',
            'repo_names.*' => 'string',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();

        $data = [
            'organization_id' => $repo->organization_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'tools' => $validated['tools'] ?? [],
            'events' => $validated['events'] ?? [],
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

        $repoIds = [$repo->id];

        if (! empty($validated['repo_names'])) {
            $additionalRepoIds = Repo::where('source', 'github')
                ->whereIn('source_reference', $validated['repo_names'])
                ->where('organization_id', $repo->organization_id)
                ->pluck('id')
                ->toArray();

            $repoIds = array_unique(array_merge($repoIds, $additionalRepoIds));
        }

        $agent->repos()->sync($repoIds);

        return Response::text(json_encode($agent->load('repos')->toArray(), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema->string()
                ->description('The repository in owner/repo format. The agent will be created in this repo\'s organization and attached to it.')
                ->required(),
            'name' => $schema->string()
                ->description('The name of the agent.')
                ->required(),
            'description' => $schema->string()
                ->description('A description of what the agent does, used as its system instructions.'),
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
            'repo_names' => $schema->array()
                ->description('Additional repository full names (owner/repo) to attach the agent to.'),
        ];
    }
}
