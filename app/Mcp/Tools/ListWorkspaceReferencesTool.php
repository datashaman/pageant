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

#[Description('List references in a workspace.')]
#[IsReadOnly]
#[IsOpenWorld]
class ListWorkspaceReferencesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace_id' => 'required|string',
        ]);

        $workspace = Workspace::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['workspace_id']);

        $references = $workspace->references()
            ->get(['id', 'source', 'source_reference', 'source_url']);

        return Response::text(json_encode($references, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
        ];
    }
}
