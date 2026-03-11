<?php

namespace App\Mcp\Tools;

use App\Concerns\ResolvesGithubInstallation;
use App\Services\GitHubService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Add one or more labels to a GitHub issue.')]
#[IsIdempotent]
#[IsOpenWorld]
class AddLabelsToIssueTool extends Tool
{
    use ResolvesGithubInstallation;

    public function __construct(
        protected GitHubService $github,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'repo' => 'required|string',
            'issue_number' => 'required|integer|min:1',
            'labels' => 'required|array|min:1',
            'labels.*' => 'required|string',
        ]);

        [, $installation] = $this->resolveInstallation($validated['repo']);

        $labels = $this->github->addLabelsToIssue($installation, $validated['repo'], $validated['issue_number'], $validated['labels']);

        return Response::text(json_encode($labels, JSON_PRETTY_PRINT));
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
            'issue_number' => $schema->integer()
                ->description('The issue number.')
                ->required(),
            'labels' => $schema->array()
                ->items($schema->string())
                ->description('Label names to add to the issue.')
                ->required(),
        ];
    }
}
