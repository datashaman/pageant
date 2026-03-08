<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateProjectTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Create a new project.';
    }

    public function handle(Request $request): string
    {
        $project = Project::create([
            'organization_id' => $this->user->currentOrganizationId(),
            'name' => $request['name'],
            'description' => $request['description'] ?? '',
        ]);

        return json_encode($project, JSON_PRETTY_PRINT);
    }

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
