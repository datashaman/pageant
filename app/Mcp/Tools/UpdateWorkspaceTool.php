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

#[Description('Update a workspace.')]
#[IsIdempotent]
#[IsOpenWorld]
class UpdateWorkspaceTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workspace = Workspace::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['id']);

        $data = array_filter([
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
        ], fn ($value) => $value !== null);

        $workspace->update($data);

        return Response::text(json_encode($workspace->fresh(), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
            'name' => $schema->string()
                ->description('The new workspace name.'),
            'description' => $schema->string()
                ->description('The new workspace description.'),
        ];
    }
}
