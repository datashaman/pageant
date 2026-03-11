<?php

namespace App\Mcp\Tools;

use App\Models\GithubInstallation;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Delete a label from a GitHub repository. This removes the label definition entirely.')]
#[IsDestructive]
#[IsOpenWorld]
class DeleteLabelTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'name' => 'required|string',
        ]);

        $ref = WorkspaceReference::where('source', 'github')
            ->whereHas('workspace', fn ($q) => $q->forCurrentOrganization())
            ->where(function ($q) use ($validated) {
                $q->where('source_reference', $validated['repo'])
                    ->orWhere('source_reference', 'LIKE', $validated['repo'].'#%');
            })
            ->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $ref->workspace->organization_id)->firstOrFail();

        $this->github->deleteLabel($installation, $validated['repo'], $validated['name']);

        return Response::text("Label '{$validated['name']}' deleted from {$validated['repo']}.");
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema->string()
                ->description('The repository in owner/repo format.')
                ->required(),
            'name' => $schema->string()
                ->description('The label name to delete.')
                ->required(),
        ];
    }
}
