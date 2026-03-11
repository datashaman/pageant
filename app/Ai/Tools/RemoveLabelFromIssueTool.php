<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RemoveLabelFromIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Remove a label from a GitHub issue.';
    }

    public function handle(Request $request): string
    {
        $this->github->removeLabelFromIssue(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
            $request['label'],
        );

        return "Label '{$request['label']}' removed from issue #{$request['issue_number']}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The issue number.')
                ->required(),
            'label' => $schema->string()
                ->description('The label name to remove.')
                ->required(),
        ];
    }
}
