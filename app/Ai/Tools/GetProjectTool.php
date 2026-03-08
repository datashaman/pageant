<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetProjectTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Get a project by ID with its repos.';
    }

    public function handle(Request $request): string
    {
        $project = Project::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['id']);

        return json_encode($project->load('repos'), JSON_PRETTY_PRINT);
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
