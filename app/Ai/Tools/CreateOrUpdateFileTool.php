<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateOrUpdateFileTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Create or update a file in a GitHub repository by committing directly to a branch. To update an existing file, provide its current SHA.';
    }

    public function handle(Request $request): string
    {
        $result = $this->github->createOrUpdateFile(
            $this->installation,
            $this->repoFullName,
            $request['path'],
            $request['content'],
            $request['message'],
            $request['branch'],
            $request['sha'] ?? null,
        );

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('The file path within the repository (e.g. "src/index.js").')
                ->required(),
            'content' => $schema->string()
                ->description('The new file content (plain text, will be base64-encoded automatically).')
                ->required(),
            'message' => $schema->string()
                ->description('The commit message.')
                ->required(),
            'branch' => $schema->string()
                ->description('The branch to commit to.')
                ->required(),
            'sha' => $schema->string()
                ->description('The SHA of the file being replaced. Required when updating an existing file.'),
        ];
    }
}
