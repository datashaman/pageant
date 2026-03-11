<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCommitStatusTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Get the combined commit status for a ref (branch, tag, or SHA). Shows CI/CD pipeline status.';
    }

    public function handle(Request $request): string
    {
        $status = $this->github->getCommitStatus(
            $this->installation,
            $this->repoFullName,
            $request['ref'],
        );

        return json_encode($status, JSON_PRETTY_PRINT);
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
