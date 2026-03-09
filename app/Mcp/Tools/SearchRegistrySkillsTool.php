<?php

namespace App\Mcp\Tools;

use App\Services\SkillRegistryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search public skill registries (MCP Registry, Smithery) for skills and MCP servers to import.')]
#[IsReadOnly]
#[IsOpenWorld]
class SearchRegistrySkillsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'registry' => 'nullable|string|in:mcp-registry,smithery,all',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $service = app(SkillRegistryService::class);
        $query = $validated['query'];
        $limit = $validated['limit'] ?? 20;
        $registry = $validated['registry'] ?? 'all';

        $results = match ($registry) {
            'mcp-registry' => $service->searchMcpRegistry($query, $limit),
            'smithery' => $service->searchSmithery($query, $limit),
            default => $service->search($query, $limit),
        };

        return Response::text(json_encode([
            'count' => $results->count(),
            'results' => $results->values()->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search query to find skills in public registries.')
                ->required(),
            'registry' => $schema->string()
                ->description('Which registry to search: "mcp-registry", "smithery", or "all" (default).'),
            'limit' => $schema->integer()
                ->description('Maximum number of results to return (default: 20, max: 50).'),
        ];
    }
}
