<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use App\Models\Repo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Attach a repository to a project.')]
#[IsIdempotent]
#[IsOpenWorld]
class AttachRepoToProjectTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => 'required|string',
            'repo_id' => 'required|string',
        ]);

        $project = Project::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['project_id']);

        $repo = Repo::query()
            ->forCurrentOrganization()
            ->findOrFail($validated['repo_id']);

        $project->repos()->syncWithoutDetaching([$repo->id]);

        return Response::text(json_encode($project->load('repos'), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('The project ID.')
                ->required(),
            'repo_id' => $schema->string()
                ->description('The repository ID to attach.')
                ->required(),
        ];
    }
}
