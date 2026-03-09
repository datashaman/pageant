<?php

namespace App\Mcp\Tools;

use App\Models\Skill;
use App\Services\SkillRegistryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search for skills by capability, name, or description. Can also search public registries.')]
#[IsReadOnly]
#[IsOpenWorld]
class SearchSkillsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'allowed_tools' => 'nullable|array',
            'allowed_tools.*' => 'string',
            'include_registry' => 'nullable|boolean',
        ]);

        $query = Skill::query()
            ->forCurrentOrganization()
            ->where('enabled', true)
            ->with('agents');

        if (! empty($validated['query'])) {
            $search = $validated['query'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('context', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['allowed_tools'])) {
            $tools = $validated['allowed_tools'];
            $query->where(function ($q) use ($tools) {
                foreach ($tools as $tool) {
                    $q->orWhereJsonContains('allowed_tools', $tool);
                }
            });
        }

        $skills = $query->get();

        $result = [
            'count' => $skills->count(),
            'skills' => $skills->toArray(),
        ];

        if (! empty($validated['include_registry']) && ! empty($validated['query'])) {
            $registryResults = app(SkillRegistryService::class)->search($validated['query'], 10);
            $result['registry_results'] = [
                'count' => $registryResults->count(),
                'results' => $registryResults->values()->toArray(),
            ];
        }

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Free-text search query to match against skill name, description, and context.'),
            'allowed_tools' => $schema->array()
                ->items($schema->string())
                ->description('Filter skills by the tools they are allowed to use.'),
            'include_registry' => $schema->boolean()
                ->description('Also search public registries (MCP Registry, Smithery) for matching skills. Defaults to false.'),
        ];
    }
}
