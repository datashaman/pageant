<?php

namespace App\Mcp\Tools;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List workspaces in the current organization.')]
#[IsReadOnly]
#[IsOpenWorld]
class ListWorkspacesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $workspaces = Workspace::query()
            ->forCurrentOrganization()
            ->get(['id', 'name', 'description']);

        return Response::text(json_encode($workspaces, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
