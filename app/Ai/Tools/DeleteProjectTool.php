<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteProjectTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Delete a project.';
    }

    public function handle(Request $request): string
    {
        $project = Project::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['id']);

        $name = $project->name;
        $project->delete();

        return "Project '{$name}' deleted successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The project ID.')
                ->required(),
        ];
    }
}
