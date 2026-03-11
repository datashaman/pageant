<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListWorkspacesTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'List workspaces in the current organization.';
    }

    public function handle(Request $request): string
    {
        $workspaces = Workspace::query()
            ->forCurrentOrganization($this->user)
            ->get(['id', 'name', 'description']);

        return json_encode($workspaces, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
