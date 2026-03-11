<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListIssuesTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'List open issues on a GitHub repository (excludes pull requests).';
    }

    public function handle(Request $request): string
    {
        $issues = $this->github->listIssues($this->installation, $this->repoFullName);

        return json_encode($issues, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
