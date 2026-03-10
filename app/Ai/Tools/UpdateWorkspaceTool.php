<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateWorkspaceTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Update a workspace.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['id']);

        $data = array_filter([
            'name' => $request['name'] ?? null,
            'description' => $request['description'] ?? null,
        ], fn ($value) => $value !== null);

        $workspace->update($data);

        return json_encode($workspace->fresh(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
            'name' => $schema->string()
                ->description('The new workspace name.'),
            'description' => $schema->string()
                ->description('The new workspace description.'),
        ];
    }
}
