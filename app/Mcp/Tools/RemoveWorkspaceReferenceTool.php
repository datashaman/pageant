<?php

namespace App\Mcp\Tools;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Remove a reference from a workspace.')]
#[IsDestructive]
#[IsOpenWorld]
class RemoveWorkspaceReferenceTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace_id' => 'required|string',
            'reference_id' => 'required|string',
        ]);

        $workspace = Workspace::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['workspace_id']);

        $reference = $workspace->references()->findOrFail($validated['reference_id']);
        $reference->delete();

        return Response::text("Reference removed from workspace '{$workspace->name}' successfully.");
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
            'reference_id' => $schema->string()
                ->description('The reference ID to remove.')
                ->required(),
        ];
    }
}
