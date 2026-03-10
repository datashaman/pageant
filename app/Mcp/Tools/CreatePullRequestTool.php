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
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create a new pull request on a GitHub repository.')]
#[IsOpenWorld]
class CreatePullRequestTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'title' => 'required|string',
            'head' => 'required|string',
            'base' => 'required|string',
            'body' => 'nullable|string',
            'draft' => 'nullable|boolean',
        ]);

        $ref = WorkspaceReference::where('source', 'github')
            ->where(function ($q) use ($validated) {
                $q->where('source_reference', $validated['repo'])
                    ->orWhere('source_reference', 'LIKE', $validated['repo'].'#%');
            })
            ->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $ref->workspace->organization_id)->firstOrFail();

        $data = [
            'title' => $validated['title'],
            'head' => $validated['head'],
            'base' => $validated['base'],
        ];

        foreach (['body', 'draft'] as $field) {
            if (isset($validated[$field])) {
                $data[$field] = $validated[$field];
            }
        }

        $pr = $this->github->createPullRequest($installation, $validated['repo'], $data);

        return Response::text(json_encode($pr, JSON_PRETTY_PRINT));
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
            'title' => $schema->string()
                ->description('The pull request title.')
                ->required(),
            'head' => $schema->string()
                ->description('The branch containing the changes (e.g. "feature-branch").')
                ->required(),
            'base' => $schema->string()
                ->description('The branch to merge into (e.g. "main").')
                ->required(),
            'body' => $schema->string()
                ->description('The pull request body/description in Markdown.'),
            'draft' => $schema->boolean()
                ->description('Whether to create the pull request as a draft.'),
        ];
    }
}
