<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateBranchTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Create a new branch on a GitHub repository from a given SHA.';
    }

    public function handle(Request $request): string
    {
        $ref = $this->github->createBranch(
            $this->installation,
            $this->repoFullName,
            $request['branch'],
            $request['sha'],
        );

        return json_encode($ref, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'branch' => $schema->string()
                ->description('The new branch name.')
                ->required(),
            'sha' => $schema->string()
                ->description('The SHA of the commit to branch from.')
                ->required(),
        ];
    }
}
