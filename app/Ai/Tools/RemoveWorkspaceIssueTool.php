<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RemoveWorkspaceIssueTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Remove an issue reference from a workspace (does not delete the GitHub issue).';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['workspace_id']);

        $reference = $workspace->references()->findOrFail($request['reference_id']);

        $sourceReference = $reference->source_reference;
        $reference->delete();

        return "Issue reference '{$sourceReference}' removed from workspace '{$workspace->name}' successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
            'reference_id' => $schema->string()
                ->description('The reference ID of the issue to remove.')
                ->required(),
        ];
    }
}
