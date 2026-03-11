<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListIssueLabelsTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'List all labels on a specific GitHub issue.';
    }

    public function handle(Request $request): string
    {
        $labels = $this->github->listIssueLabels(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
        );

        return json_encode($labels, JSON_PRETTY_PRINT);
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
