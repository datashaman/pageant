<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create a new project.')]
#[IsOpenWorld]
class CreateProjectTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = Project::create([
            'organization_id' => auth()->user()->currentOrganizationId(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
        ]);

        return Response::text(json_encode($project, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The project name.')
                ->required(),
            'description' => $schema->string()
                ->description('An optional project description.'),
        ];
    }
}
