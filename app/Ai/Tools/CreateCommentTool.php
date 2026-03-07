<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateCommentTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Add a comment to a GitHub issue or pull request.';
    }

    public function handle(Request $request): string
    {
        $comment = $this->github->createComment(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
            $request['body'],
        );

        return json_encode($comment, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The issue or pull request number.')
                ->required(),
            'body' => $schema->string()
                ->description('The comment body in Markdown.')
                ->required(),
        ];
    }
}
