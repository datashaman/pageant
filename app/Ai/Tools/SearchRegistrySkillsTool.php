<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\SkillRegistryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchRegistrySkillsTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Search public skill registries (MCP Registry, Smithery) for skills and MCP servers to import.';
    }

    public function handle(Request $request): string
    {
        $service = app(SkillRegistryService::class);
        $query = $request['query'] ?? '';
        $limit = $request['limit'] ?? 20;
        $registry = $request['registry'] ?? 'all';

        $results = match ($registry) {
            'mcp-registry' => $service->searchMcpRegistry($query, $limit),
            'smithery' => $service->searchSmithery($query, $limit),
            default => $service->search($query, $limit),
        };

        return json_encode([
            'count' => $results->count(),
            'results' => $results->values()->toArray(),
        ], JSON_PRETTY_PRINT);
    }

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
