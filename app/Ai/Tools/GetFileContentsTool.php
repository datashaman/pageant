<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetFileContentsTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Get the contents of a file from a GitHub repository.';
    }

    public function handle(Request $request): string
    {
        $contents = $this->github->getFileContents(
            $this->installation,
            $this->repoFullName,
            $request['path'],
            $request['ref'] ?? null,
        );

        if (isset($contents['content']) && isset($contents['encoding']) && $contents['encoding'] === 'base64') {
            $contents['decoded_content'] = base64_decode($contents['content']);
            unset($contents['content']);
        }

        return json_encode($contents, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('The file path within the repository (e.g. "src/index.js").')
                ->required(),
            'ref' => $schema->string()
                ->description('Branch name, tag, or commit SHA. Defaults to the default branch.'),
        ];
    }
}
