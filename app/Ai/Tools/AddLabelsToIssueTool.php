<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddLabelsToIssueTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Add one or more labels to a GitHub issue.';
    }

    public function handle(Request $request): string
    {
        $labels = $this->github->addLabelsToIssue(
            $this->installation,
            $this->repoFullName,
            (int) $request['issue_number'],
            $request['labels'],
        );

        return json_encode($labels, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_number' => $schema->integer()
                ->description('The issue number.')
                ->required(),
            'labels' => $schema->array()
                ->items($schema->string())
                ->description('Label names to add to the issue.')
                ->required(),
        ];
    }
}
