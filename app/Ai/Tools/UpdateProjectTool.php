<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateProjectTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Update a project.';
    }

    public function handle(Request $request): string
    {
        $project = Project::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['id']);

        $data = array_filter([
            'name' => $request['name'] ?? null,
            'description' => $request['description'] ?? null,
        ], fn ($value) => $value !== null);

        $project->update($data);

        return json_encode($project->fresh(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The project ID.')
                ->required(),
            'name' => $schema->string()
                ->description('The new project name.'),
            'description' => $schema->string()
                ->description('The new project description.'),
        ];
    }
}
