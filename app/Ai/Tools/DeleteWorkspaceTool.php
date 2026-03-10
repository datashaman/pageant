<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteWorkspaceTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Delete a workspace.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['id']);

        $name = $workspace->name;
        $workspace->delete();

        return "Workspace '{$name}' deleted successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
        ];
    }
}
