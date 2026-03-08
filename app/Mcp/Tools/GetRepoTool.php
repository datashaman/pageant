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

#[Description('Get a repository by ID.')]
#[IsReadOnly]
#[IsOpenWorld]
class GetRepoTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $repo = Repo::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['id']);

        return Response::text(json_encode($repo, JSON_PRETTY_PRINT));
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
