<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteLabelTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
        protected ?User $user = null,
    ) {}

    public function description(): string
    {
        return 'Delete a label from a GitHub repository. This removes the label definition entirely.';
    }

    public function handle(Request $request): string
    {
        $this->github->deleteLabel(
            $this->installation,
            $this->repoFullName,
            $request['name'],
        );

        return "Label '{$request['name']}' deleted successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The label name to delete.')
                ->required(),
        ];
    }
}
