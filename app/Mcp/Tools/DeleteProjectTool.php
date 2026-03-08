<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Delete a project.')]
#[IsDestructive]
#[IsOpenWorld]
class DeleteProjectTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $project = Project::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['id']);

        $name = $project->name;
        $project->delete();

        return Response::text("Project '{$name}' deleted successfully.");
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
