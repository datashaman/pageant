<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteWorkItemTool implements Tool
{
    public function __construct(
        protected \App\Services\GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Delete a work item that was created from a GitHub issue.';
    }

    public function handle(Request $request): string
    {
        $repo = Repo::where('source', 'github')
            ->where('source_reference', $this->repoFullName)
            ->firstOrFail();

        $sourceReference = $this->repoFullName.'#'.$request['issue_number'];

        $workItem = WorkItem::where('organization_id', $repo->organization_id)
            ->where('source', 'github')
            ->where('source_reference', $sourceReference)
            ->firstOrFail();

        $workItem->delete();

        return "Work item for {$sourceReference} deleted successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The GitHub issue number of the work item to delete.')
                ->required(),
        ];
    }
}
