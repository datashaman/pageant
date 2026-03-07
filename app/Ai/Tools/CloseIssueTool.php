<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CloseIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Close a GitHub issue with an optional reason (completed or not_planned).';
    }

    public function handle(Request $request): string
    {
        $data = [
            'state' => 'closed',
            'state_reason' => $request['state_reason'] ?? 'completed',
        ];

        $issue = $this->github->updateIssue(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
            $data,
        );

        return json_encode($issue, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The issue number to close.')
                ->required(),
            'state_reason' => $schema->string()
                ->enum(['completed', 'not_planned'])
                ->description('Reason for closing.'),
        ];
    }
}
