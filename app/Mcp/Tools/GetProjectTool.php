<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get a project by ID with its repos.')]
#[IsReadOnly]
#[IsOpenWorld]
class GetProjectTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $project = Project::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['id']);

        return Response::text(json_encode($project->load('repos'), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The project ID.')
                ->required(),
        ];
    }
}
