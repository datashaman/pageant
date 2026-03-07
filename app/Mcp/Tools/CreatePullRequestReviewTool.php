<?php

namespace App\Mcp\Tools;

use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Submit a review on a pull request with optional line-level comments: approve, request changes, or comment.')]
#[IsOpenWorld]
class CreatePullRequestReviewTool extends Tool
{
    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'pull_number' => 'required|integer|min:1',
            'event' => 'required|string|in:APPROVE,REQUEST_CHANGES,COMMENT',
            'body' => 'nullable|string',
            'comments' => 'nullable|array',
            'comments.*.path' => 'required|string',
            'comments.*.body' => 'required|string',
            'comments.*.line' => 'required|integer',
            'comments.*.side' => 'nullable|string|in:LEFT,RIGHT',
            'comments.*.start_line' => 'nullable|integer',
            'comments.*.start_side' => 'nullable|string|in:LEFT,RIGHT',
        ]);

        $repo = Repo::where('source', 'github')->where('source_reference', $validated['repo'])->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $review = $this->github->createPullRequestReview(
            $installation,
            $validated['repo'],
            $validated['pull_number'],
            $validated['event'],
            $validated['body'] ?? null,
            $validated['comments'] ?? [],
        );

        return Response::text(json_encode($review, JSON_PRETTY_PRINT));
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
            'pull_number' => $schema->integer()
                ->description('The pull request number.')
                ->required(),
            'event' => $schema->string()
                ->enum(['APPROVE', 'REQUEST_CHANGES', 'COMMENT'])
                ->description('The review action to perform.')
                ->required(),
            'body' => $schema->string()
                ->description('Review comment body. Required for REQUEST_CHANGES and COMMENT.'),
            'comments' => $schema->array()
                ->description('Line-level review comments. Each object: {path: string, body: string, line: int, side?: "LEFT"|"RIGHT", start_line?: int, start_side?: "LEFT"|"RIGHT"}. "line" is the line in the diff to comment on. Use start_line for multi-line comments.'),
        ];
    }
}
