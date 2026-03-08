<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReopenWorkItemTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected ?GithubInstallation $installation = null,
        protected ?string $repoFullName = null,
    ) {}

    public function description(): string
    {
        return 'Reopen a closed work item.';
    }

    public function handle(Request $request): string
    {
        $repoFullName = $this->repoFullName ?? $request['repo'];

        $repo = Repo::where('source', 'github')
            ->where('source_reference', $repoFullName)
            ->firstOrFail();

        $sourceReference = $repoFullName.'#'.$request['issue_number'];

        $workItem = WorkItem::where('organization_id', $repo->organization_id)
            ->where('source', 'github')
            ->where('source_reference', $sourceReference)
            ->firstOrFail();

        $workItem->update(['status' => 'open']);

        return "Work item for {$sourceReference} reopened successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = [];

        if (! $this->repoFullName) {
            $fields['repo'] = $schema->string()
                ->description('The repository in owner/repo format.')
                ->required();
        }

        return array_merge($fields, [
            'issue_number' => $schema->integer()
                ->description('The GitHub issue number of the work item to reopen.')
                ->required(),
        ]);
    }
}
