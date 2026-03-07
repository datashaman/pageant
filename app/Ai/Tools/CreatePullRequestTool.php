<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreatePullRequestTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Create a new pull request on a GitHub repository.';
    }

    public function handle(Request $request): string
    {
        $data = [
            'title' => $request['title'],
            'head' => $request['head'],
            'base' => $request['base'],
        ];

        foreach (['body', 'draft'] as $field) {
            if (isset($request[$field])) {
                $data[$field] = $request[$field];
            }
        }

        $pr = $this->github->createPullRequest($this->installation, $this->repoFullName, $data);

        return json_encode($pr, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
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
