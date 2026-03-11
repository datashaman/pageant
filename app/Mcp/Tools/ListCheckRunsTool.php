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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List check runs (CI/CD checks) for a ref. Shows test results, build status, and other checks.')]
#[IsReadOnly]
#[IsOpenWorld]
class ListCheckRunsTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'ref' => 'required|string',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $checkRuns = $this->github->listCheckRuns($installation, $validated['repo'], $validated['ref']);

        return Response::text(json_encode($checkRuns, JSON_PRETTY_PRINT));
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
            'ref' => $schema->string()
                ->description('A branch name, tag, or commit SHA.')
                ->required(),
        ];
    }
}
