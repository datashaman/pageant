<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\Workspace;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateWorkspaceIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected ?GithubInstallation $installation = null,
        protected ?string $repoFullName = null,
    ) {}

    public function description(): string
    {
        return 'Create a GitHub issue and add it as a reference in the workspace.';
    }

    public function handle(Request $request): string
    {
        $workspace = Workspace::query()->forCurrentOrganization()->findOrFail($request['workspace_id']);

        $repoFullName = $this->repoFullName;

        if (! $repoFullName) {
            $repoRef = $workspace->references()
                ->where('source', 'github')
                ->whereRaw("source_reference NOT LIKE '%#%'")
                ->first();

            if (! $repoRef) {
                return json_encode(['error' => 'No repository reference found in this workspace. Add a repo reference first.']);
            }

            $repoFullName = $repoRef->source_reference;
        }

        $installation = $this->installation
            ?? GithubInstallation::where('organization_id', $workspace->organization_id)->firstOrFail();

        $data = ['title' => $request['title']];

        if (isset($request['body'])) {
            $data['body'] = $request['body'];
        }

        if (isset($request['labels'])) {
            $data['labels'] = $request['labels'];
        }

        $issue = $this->github->createIssue($installation, $repoFullName, $data);

        $reference = $workspace->references()->updateOrCreate(
            ['source_reference' => $repoFullName.'#'.$issue['number']],
            [
                'source' => 'github',
                'source_url' => $issue['html_url'] ?? '',
            ],
        );

        return json_encode([
            'issue' => $issue,
            'reference' => $reference->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('The workspace ID.')
                ->required(),
            'title' => $schema->string()
                ->description('The issue title.')
                ->required(),
            'body' => $schema->string()
                ->description('The issue body/description in Markdown.'),
            'labels' => $schema->array()
                ->items($schema->string())
                ->description('Label names to apply to the issue.'),
        ];
    }
}
