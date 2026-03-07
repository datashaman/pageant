<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListCheckRunsTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'List check runs (CI/CD checks) for a ref. Shows test results, build status, and other checks.';
    }

    public function handle(Request $request): string
    {
        $checkRuns = $this->github->listCheckRuns(
            $this->installation,
            $this->repoFullName,
            $request['ref'],
        );

        return json_encode($checkRuns, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ref' => $schema->string()
                ->description('A branch name, tag, or commit SHA.')
                ->required(),
        ];
    }
}
