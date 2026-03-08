<?php

namespace App\Ai\Tools;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchSkillsTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Search for skills by capability, name, or description.';
    }

    public function handle(Request $request): string
    {
        $query = Skill::forCurrentOrganization($this->user)
            ->where('enabled', true)
            ->with('agents');

        if (! empty($request['query'])) {
            $search = $request['query'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('context', 'like', "%{$search}%");
            });
        }

        if (! empty($request['allowed_tools'])) {
            $tools = $request['allowed_tools'];
            $query->where(function ($q) use ($tools) {
                foreach ($tools as $tool) {
                    $q->orWhereJsonContains('allowed_tools', $tool);
                }
            });
        }

        $skills = $query->get();

        return json_encode([
            'count' => $skills->count(),
            'skills' => $skills->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Free-text search query to match against skill name, description, and context.'),
            'allowed_tools' => $schema->array()
                ->items($schema->string())
                ->description('Filter skills by the tools they are allowed to use.'),
        ];
    }
}
