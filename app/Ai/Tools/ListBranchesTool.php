<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListBranchesTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'List all branches on a GitHub repository.';
    }

    public function handle(Request $request): string
    {
        $branches = $this->github->listBranches($this->installation, $this->repoFullName);

        return json_encode($branches, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
