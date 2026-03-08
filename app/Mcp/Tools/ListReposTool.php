<?php

namespace App\Mcp\Tools;

use App\Models\Repo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List repositories in the current organization.')]
#[IsReadOnly]
#[IsOpenWorld]
class ListReposTool extends Tool
{
    public function handle(Request $request): Response
    {
        $repos = Repo::query()
            ->forCurrentOrganization()
            ->get(['id', 'name', 'source', 'source_reference', 'source_url']);

        return Response::text(json_encode($repos, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
