<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetRepositoryTreeTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'List files and directories in a repository tree. Use a branch name, tag, or commit SHA as the tree_sha parameter.';
    }

    public function handle(Request $request): string
    {
        $tree = $this->github->getRepositoryTree(
            $this->installation,
            $this->repoFullName,
            $request['tree_sha'],
            (bool) ($request['recursive'] ?? false),
        );

        return json_encode($tree, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tree_sha' => $schema->string()
                ->description('The SHA of the tree, or a branch/tag name (e.g. "main").')
                ->required(),
            'recursive' => $schema->boolean()
                ->description('If true, returns all files recursively. Defaults to false.'),
        ];
    }
}
