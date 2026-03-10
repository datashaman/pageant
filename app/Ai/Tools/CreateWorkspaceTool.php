<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateWorkspaceTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Create a new workspace.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::create([
            'organization_id' => $this->user->currentOrganizationId(),
            'name' => $request['name'],
            'description' => $request['description'] ?? '',
        ]);

        return json_encode($workspace, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The workspace name.')
                ->required(),
            'description' => $schema->string()
                ->description('An optional workspace description.'),
        ];
    }
}
