<?php

namespace App\Ai\Tools;

use App\Models\GithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateLabelTool implements Tool
{
    public function __construct(
        protected GitHubService $github,
        protected GithubInstallation $installation,
        protected string $repoFullName,
    ) {}

    public function description(): string
    {
        return 'Create a new label on a GitHub repository.';
    }

    public function handle(Request $request): string
    {
        $label = $this->github->createLabel(
            $this->installation,
            $this->repoFullName,
            $request['name'],
            $request['color'],
            $request['description'] ?? null,
        );

        return json_encode($label, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The label name.')
                ->required(),
            'color' => $schema->string()
                ->description('The label color as 6-char hex code (without #).')
                ->required(),
            'description' => $schema->string()
                ->description('An optional description for the label.'),
        ];
    }
}
