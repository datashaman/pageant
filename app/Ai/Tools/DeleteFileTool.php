<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteFileTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Delete a file from a GitHub repository by committing the deletion to a branch.';
    }

    public function handle(Request $request): string
    {
        $result = $this->github->deleteFile(
            $this->installation,
            $this->repoFullName,
            $request['path'],
            $request['message'],
            $request['branch'],
            $request['sha'],
        );

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('The file path to delete.')
                ->required(),
            'message' => $schema->string()
                ->description('The commit message for the deletion.')
                ->required(),
            'branch' => $schema->string()
                ->description('The branch to commit the deletion to.')
                ->required(),
            'sha' => $schema->string()
                ->description('The SHA of the file to delete.')
                ->required(),
        ];
    }
}
