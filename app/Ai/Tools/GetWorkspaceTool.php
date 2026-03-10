<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetWorkspaceTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Get a workspace by ID with its references.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::query()
            ->forCurrentOrganization($this->user)
            ->with('references')
            ->findOrFail($request['id']);

        return json_encode($workspace, JSON_PRETTY_PRINT);
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
