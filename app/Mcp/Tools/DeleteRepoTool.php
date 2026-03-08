<?php

namespace App\Mcp\Tools;

use App\Models\Repo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Delete a repository.')]
#[IsDestructive]
#[IsOpenWorld]
class DeleteRepoTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $repo = Repo::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['id']);

        $name = $repo->name;
        $repo->delete();

        return Response::text("Repository '{$name}' deleted successfully.");
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
        ];
    }
}
