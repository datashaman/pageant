<?php

namespace App\Mcp\Tools;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Add a reference to a workspace.')]
#[IsIdempotent]
#[IsOpenWorld]
class AddWorkspaceReferenceTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'workspace_id' => 'required|string',
            'source' => 'required|string',
            'source_reference' => 'required|string',
            'source_url' => 'nullable|string|url',
        ]);

        $workspace = Workspace::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['workspace_id']);

        $reference = $workspace->references()->firstOrCreate(
            [
                'source' => $validated['source'],
                'source_reference' => $validated['source_reference'],
            ],
            [
                'source_url' => $validated['source_url'] ?? '',
            ]
        );

        return Response::text(json_encode($reference, JSON_PRETTY_PRINT));
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
            'source' => $schema->string()
                ->description('The source type (e.g. "github").')
                ->required(),
            'source_reference' => $schema->string()
                ->description('The source reference (e.g. "owner/repo" or "owner/repo#123").')
                ->required(),
            'source_url' => $schema->string()
                ->description('An optional URL for the reference.'),
        ];
    }
}
