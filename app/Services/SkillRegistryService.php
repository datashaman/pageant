<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SkillRegistryService
{
    private const MCP_REGISTRY_BASE = 'https://registry.modelcontextprotocol.io/v0';

    private const SMITHERY_API_BASE = 'https://api.smithery.ai';

    /**
     * Search across all configured registries.
     *
     * @return Collection<int, array{
     *     registry: string,
     *     name: string,
     *     description: string,
     *     source_url: string,
     *     source_reference: string,
     *     repository_url: string|null,
     *     version: string|null,
     * }>
     */
    public function search(string $query, int $limit = 10): Collection
    {
        $results = $this->searchMcpRegistry($query, $limit);

        $smitheryKey = config('services.smithery.api_key');
        if ($smitheryKey) {
            $results = $results->merge($this->searchSmithery($query, $limit, $smitheryKey));
        }

        return $results
            ->sortBy(static fn (array $item): string => mb_strtolower($item['name'] ?? ''))
            ->values()
            ->take($limit);
    }

    /**
     * Search the official MCP Registry.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function searchMcpRegistry(string $query, int $limit = 20): Collection
    {
        try {
            $response = Http::timeout(10)
                ->get(self::MCP_REGISTRY_BASE.'/servers', [
                    'search' => $query,
                    'limit' => min($limit, 100),
                ]);

            if (! $response->successful()) {
                Log::warning('MCP Registry search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return collect();
            }

            $data = $response->json();
            $servers = $data['servers'] ?? [];

            return collect($servers)->map(function (array $entry) {
                $server = $entry['server'];
                $name = $server['name'] ?? '';
                $description = $server['description'] ?? '';
                $repoUrl = $server['repository']['url'] ?? null;

                return [
                    'registry' => 'mcp-registry',
                    'name' => $name,
                    'description' => $description,
                    'source_url' => $repoUrl ?? '',
                    'source_reference' => $name,
                    'repository_url' => $repoUrl,
                    'version' => $server['version'] ?? null,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('MCP Registry search error', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return collect();
        }
    }

    /**
     * Search the Smithery registry.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function searchSmithery(string $query, int $limit = 20, ?string $apiKey = null): Collection
    {
        $apiKey ??= config('services.smithery.api_key');

        if (! $apiKey) {
            return collect();
        }

        try {
            $response = Http::timeout(10)
                ->withToken($apiKey)
                ->get(self::SMITHERY_API_BASE.'/servers', [
                    'q' => $query,
                    'pageSize' => min($limit, 100),
                ]);

            if (! $response->successful()) {
                Log::warning('Smithery search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return collect();
            }

            $data = $response->json();
            $servers = $data['servers'] ?? [];

            return collect($servers)->map(function (array $server) {
                $qualifiedName = $server['qualifiedName'] ?? '';
                $displayName = $server['displayName'] ?? $qualifiedName;
                $description = $server['description'] ?? '';
                $homepage = $server['homepage'] ?? '';

                return [
                    'registry' => 'smithery',
                    'name' => $displayName,
                    'description' => $description,
                    'source_url' => $homepage ?: "https://smithery.ai/server/{$qualifiedName}",
                    'source_reference' => $qualifiedName,
                    'repository_url' => $server['repository'] ?? null,
                    'version' => null,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('Smithery search error', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return collect();
        }
    }
}
