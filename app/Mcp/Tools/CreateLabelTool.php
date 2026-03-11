<?php

namespace App\Mcp\Tools;

use App\Concerns\ResolvesGithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create a new label on a GitHub repository.')]
#[IsOpenWorld]
class CreateLabelTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'name' => 'required|string',
            'color' => 'required|string|regex:/^[0-9a-fA-F]{6}$/',
            'description' => 'nullable|string',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $label = $this->github->createLabel(
            $installation,
            $validated['repo'],
            $validated['name'],
            $validated['color'],
            $validated['description'] ?? null,
        );

        return Response::text(json_encode($label, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repo' => $schema->string()
                ->description('The repository in owner/repo format.')
                ->required(),
            'name' => $schema->string()
                ->description('The label name.')
                ->required(),
            'color' => $schema->string()
                ->description('The label color as a 6-character hex code (without #).')
                ->required(),
            'description' => $schema->string()
                ->description('An optional description for the label.'),
        ];
    }
}
