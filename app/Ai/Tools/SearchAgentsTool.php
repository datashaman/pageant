<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchAgentsTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Search for agents that match a work item\'s requirements based on tools, skills, and description.';
    }

    public function handle(Request $request): string
    {
        $query = Agent::forCurrentOrganization($this->user)
            ->where('enabled', true)
            ->with('skills', 'repos');

        if (! empty($request['work_item_id'])) {
            $workItem = WorkItem::forCurrentOrganization($this->user)
                ->findOrFail($request['work_item_id']);

            $stopWords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'has', 'her', 'was', 'one', 'our', 'out', 'his', 'had', 'its', 'how', 'may', 'who', 'did', 'get', 'let', 'say', 'she', 'too', 'use', 'that', 'this', 'with', 'have', 'from', 'they', 'been', 'said', 'each', 'will', 'other', 'about', 'many', 'then', 'them', 'these', 'some', 'would', 'make', 'like', 'into', 'than', 'just', 'over', 'also', 'back', 'after', 'could', 'when', 'what', 'your', 'which', 'their', 'there', 'should', 'does', 'need', 'must', 'been', 'being', 'were', 'more', 'very'];

            $searchTerms = array_unique(array_filter(
                array_merge(
                    str_word_count(strtolower($workItem->title), 1),
                    str_word_count(strtolower($workItem->description ?? ''), 1),
                ),
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

        if (! empty($request['tools'])) {
            $tools = $request['tools'];
            $query->where(function ($q) use ($tools) {
                foreach ($tools as $tool) {
                    $q->orWhereJsonContains('tools', $tool);
                }
            });
        }

        if (! empty($request['skills'])) {
            $skillNames = $request['skills'];
            $query->whereHas('skills', function ($q) use ($skillNames) {
                $q->where(function ($sq) use ($skillNames) {
                    foreach ($skillNames as $skillName) {
                        $sq->orWhere('name', 'like', "%{$skillName}%");
                    }
                });
            });
        }

        if (! empty($request['query'])) {
            $search = $request['query'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $agents = $query->get();

        return json_encode([
            'count' => $agents->count(),
            'agents' => $agents->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()
                ->description('The UUID of a work item to find matching agents for.'),
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
