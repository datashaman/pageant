<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdatePullRequestTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Update an existing pull request. Can modify title, body, state (open/closed), and base branch.';
    }

    public function handle(Request $request): string
    {
        $data = [];

        foreach (['title', 'body', 'state', 'base'] as $field) {
            if (isset($request[$field])) {
                $data[$field] = $request[$field];
            }
        }

        $pr = $this->github->updatePullRequest(
            $this->installation,
            $this->repoFullName,
            (int) $request['pull_number'],
            $data,
        );

        return json_encode($pr, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pull_number' => $schema->integer()
                ->description('The pull request number to update.')
                ->required(),
            'title' => $schema->string()
                ->description('Updated pull request title.'),
            'body' => $schema->string()
                ->description('Updated pull request body/description in Markdown.'),
            'state' => $schema->string()
                ->enum(['open', 'closed'])
                ->description('Set pull request state.'),
            'base' => $schema->string()
                ->description('The branch to merge into (change the base branch).'),
        ];
    }
}
