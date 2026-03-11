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

#[Description('Remove a GitHub issue reference from a workspace.')]
#[IsDestructive]
#[IsOpenWorld]
class RemoveWorkspaceIssueTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace_id' => 'required|string',
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
        ]);

        $workspace = Workspace::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['workspace_id']);

        $sourceReference = $validated['repo'].'#'.$validated['issue_number'];

        $reference = $workspace->references()
            ->where('source', 'github')
            ->where('source_reference', $sourceReference)
            ->firstOrFail();

        $reference->delete();

        return Response::text("Issue reference {$sourceReference} removed from workspace '{$workspace->name}' successfully.");
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
            'repo' => $schema->string()
                ->description('The repository in owner/repo format.')
                ->required(),
            'issue_number' => $schema->integer()
                ->description('The GitHub issue number to remove.')
                ->required(),
        ];
    }
}
