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
     * List popular/recent skills from the MCP Registry (no search required).
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
    public function listPopular(int $limit = 24): Collection
    {
        try {
            $response = Http::timeout(10)
                ->get(self::MCP_REGISTRY_BASE.'/servers', [
                    'limit' => min($limit * 2, 100),
                ]);

            if (! $response->successful()) {
                Log::warning('MCP Registry list failed', ['status' => $response->status()]);

                return collect();
            }

            $data = $response->json();
            $servers = $data['servers'] ?? [];

            return collect($servers)
                ->filter(fn (array $entry): bool => ($entry['_meta']['io.modelcontextprotocol.registry/official']['isLatest'] ?? false) === true)
                ->take($limit)
                ->map(fn (array $entry) => $this->mapMcpEntryToResult($entry))
                ->values();
        } catch (\Throwable $e) {
            Log::warning('MCP Registry list error', ['error' => $e->getMessage()]);

            return collect();
        }
    }

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

            return collect($servers)->map(fn (array $entry) => $this->mapMcpEntryToResult($entry));
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

    /**
     * @return array{registry: string, name: string, description: string, source_url: string, source_reference: string, repository_url: string|null, version: string|null}
     */
    private function mapMcpEntryToResult(array $entry): array
    {
        $server = $entry['server'];
        $name = $server['name'] ?? '';
        $description = $server['description'] ?? '';
        $repoUrl = ($server['repository'] ?? [])['url'] ?? null;
        $websiteUrl = $server['websiteUrl'] ?? null;

        return [
            'registry' => 'mcp-registry',
            'name' => $name,
            'description' => $description,
            'source_url' => $repoUrl ?? $websiteUrl ?? '',
            'source_reference' => $name,
            'repository_url' => $repoUrl,
            'version' => $server['version'] ?? null,
        ];
    }
}
