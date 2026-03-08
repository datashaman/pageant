<?php

namespace App\Mcp\Tools;

use App\Models\Repo;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Close a work item that was created from a GitHub issue.')]
#[IsOpenWorld]
class CloseWorkItemTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();

        $sourceReference = $validated['repo'].'#'.$validated['issue_number'];

        $workItem = WorkItem::where('organization_id', $repo->organization_id)
            ->where('source', 'github')
            ->where('source_reference', $sourceReference)
            ->firstOrFail();

        $workItem->update(['status' => 'closed']);

        return Response::text("Work item for {$sourceReference} closed successfully.");
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
            'issue_number' => $schema->integer()
                ->description('The GitHub issue number of the work item to close.')
                ->required(),
        ];
    }
}
