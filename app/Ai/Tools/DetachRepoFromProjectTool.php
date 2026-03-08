<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\Repo;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DetachRepoFromProjectTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Detach a repository from a project.';
    }

    public function handle(Request $request): string
    {
        $project = Project::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['project_id']);

        $repo = Repo::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['repo_id']);

        $project->repos()->detach($repo->id);

        return "Repository detached from project '{$project->name}' successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('The project ID.')
                ->required(),
            'repo_id' => $schema->string()
                ->description('The repository ID to detach.')
                ->required(),
        ];
    }
}
