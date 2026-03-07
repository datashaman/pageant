<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Get a single GitHub issue by number, including title, body, state, labels, and assignees.';
    }

    public function handle(Request $request): string
    {
        $issue = $this->github->getIssue(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
        );

        return json_encode($issue, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The issue number.')
                ->required(),
        ];
    }
}
