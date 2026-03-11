<?php

namespace App\Mcp\Tools;

use App\Models\Agent;
use App\Models\WorkspaceReference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search for agents that match requirements based on tools, skills, and description.')]
#[IsReadOnly]
#[IsOpenWorld]
class SearchAgentsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'reference_id' => 'nullable|string',
            'tools' => 'nullable|array',
            'tools.*' => 'string',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'query' => 'nullable|string|max:255',
        ]);

        $query = Agent::query()
            ->forCurrentOrganization()
            ->where('enabled', true)
            ->with('skills', 'workspaces');

        if (! empty($validated['reference_id'])) {
            $reference = WorkspaceReference::query()
                ->whereHas('workspace', fn ($q) => $q->forCurrentOrganization())
                ->findOrFail($validated['reference_id']);

            $stopWords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'has', 'her', 'was', 'one', 'our', 'out', 'his', 'had', 'its', 'how', 'may', 'who', 'did', 'get', 'let', 'say', 'she', 'too', 'use', 'that', 'this', 'with', 'have', 'from', 'they', 'been', 'said', 'each', 'will', 'other', 'about', 'many', 'then', 'them', 'these', 'some', 'would', 'make', 'like', 'into', 'than', 'just', 'over', 'also', 'back', 'after', 'could', 'when', 'what', 'your', 'which', 'their', 'there', 'should', 'does', 'need', 'must', 'been', 'being', 'were', 'more', 'very'];

            $searchTerms = array_unique(array_filter(
                str_word_count(strtolower($reference->source_reference), 1),
                fn (string $term) => strlen($term) >= 3 && ! in_array($term, $stopWords),
            ));

            if (! empty($searchTerms)) {
                $query->where(function ($q) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $q->orWhere('name', 'like', "%{$term}%")
                            ->orWhere('description', 'like', "%{$term}%");
                    }
                });
            }
        }

        if (! empty($validated['tools'])) {
            $tools = $validated['tools'];
            $query->where(function ($q) use ($tools) {
                foreach ($tools as $tool) {
                    $q->orWhereJsonContains('tools', $tool);
                }
            });
        }

        if (! empty($validated['skills'])) {
            $skillNames = $validated['skills'];
            $query->whereHas('skills', function ($q) use ($skillNames) {
                $q->where(function ($sq) use ($skillNames) {
                    foreach ($skillNames as $skillName) {
                        $sq->orWhere('name', 'like', "%{$skillName}%");
                    }
                });
            });
        }

        if (! empty($validated['query'])) {
            $search = $validated['query'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $agents = $query->get();

        return Response::text(json_encode([
            'count' => $agents->count(),
            'agents' => $agents->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference_id' => $schema->string()
                ->description('The UUID of a workspace reference to find matching agents for.'),
            'tools' => $schema->array()
                ->items($schema->string())
                ->description('Tool names the agent should have.'),
            'skills' => $schema->array()
                ->items($schema->string())
                ->description('Skill names or keywords the agent should have.'),
            'query' => $schema->string()
                ->description('Free-text search query to match against agent name and description.'),
        ];
    }
}
