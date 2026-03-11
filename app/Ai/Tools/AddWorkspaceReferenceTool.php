<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddWorkspaceReferenceTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Add a source reference to a workspace.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['workspace_id']);

        $reference = $workspace->references()->firstOrCreate(
            ['source_reference' => $request['source_reference']],
            [
                'source' => $request['source'] ?? 'github',
                'source_url' => $request['source_url'] ?? '',
            ],
        );

        return json_encode($reference, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
            'source' => $schema->string()
                ->description('The source type (e.g. "github"). Defaults to "github".'),
            'source_reference' => $schema->string()
                ->description('The source reference (e.g. "owner/repo" or "owner/repo#42").')
                ->required(),
            'source_url' => $schema->string()
                ->description('An optional URL for the source reference.'),
        ];
    }
}
