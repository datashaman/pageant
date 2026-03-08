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

            $searchTerms = array_filter(array_merge(
                str_word_count($workItem->title, 1),
                str_word_count($workItem->description ?? '', 1),
            ));

            if (! empty($searchTerms)) {
                $query->where(function ($q) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        if (strlen($term) < 3) {
                            continue;
                        }

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
