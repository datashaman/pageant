<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListPullRequestFilesTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'List files changed in a pull request, with additions, deletions, and status for each file.';
    }

    public function handle(Request $request): string
    {
        $files = $this->github->listPullRequestFiles(
            $this->installation,
            $this->repoFullName,
            (int) $request['pull_number'],
        );

        return json_encode($files, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pull_number' => $schema->integer()
                ->description('The pull request number.')
                ->required(),
        ];
    }
}
