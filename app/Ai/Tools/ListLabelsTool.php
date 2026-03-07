<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListLabelsTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'List all labels defined on a GitHub repository.';
    }

    public function handle(Request $request): string
    {
        $labels = $this->github->listLabels($this->installation, $this->repoFullName);

        return json_encode($labels, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
