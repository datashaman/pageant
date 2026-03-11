<?php

namespace App\Mcp\Tools;

use App\Concerns\ResolvesGithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Add a comment to a GitHub issue or pull request.')]
#[IsOpenWorld]
class CreateCommentTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
            'body' => 'required|string',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $comment = $this->github->createComment(
            $installation,
            $validated['repo'],
            $validated['issue_number'],
            $validated['body'],
        );

        return Response::text(json_encode($comment, JSON_PRETTY_PRINT));
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
                ->description('The issue or pull request number.')
                ->required(),
            'body' => $schema->string()
                ->description('The comment body in Markdown.')
                ->required(),
        ];
    }
}
