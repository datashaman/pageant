<?php

namespace App\Mcp\Tools;

use App\Models\Repo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Update a repository name.')]
#[IsIdempotent]
#[IsOpenWorld]
class UpdateRepoTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        $repo = Repo::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['id']);

        $repo->update(['name' => $validated['name']]);

        return Response::text(json_encode($repo->fresh(), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The repository ID.')
                ->required(),
            'name' => $schema->string()
                ->description('The new name for the repository.')
                ->required(),
        ];
    }
}
