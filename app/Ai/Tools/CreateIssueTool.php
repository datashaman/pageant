<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Create a new issue on a GitHub repository.';
    }

    public function handle(Request $request): string
    {
        $data = ['title' => $request['title']];

        foreach (['body', 'labels', 'assignees'] as $field) {
            if (isset($request[$field])) {
                $data[$field] = $request[$field];
            }
        }

        $issue = $this->github->createIssue($this->installation, $this->repoFullName, $data);

        return json_encode($issue, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The issue title.')
                ->required(),
            'body' => $schema->string()
                ->description('The issue body/description in Markdown.'),
            'labels' => $schema->array()
                ->items($schema->string())
                ->description('Label names to apply to the issue.'),
            'assignees' => $schema->array()
                ->items($schema->string())
                ->description('GitHub usernames to assign to the issue.'),
        ];
    }
}
