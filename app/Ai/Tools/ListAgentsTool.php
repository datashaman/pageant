<?php

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListAgentsTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'List agents in the current organization, optionally filtered by capability.';
    }

    public function handle(Request $request): string
    {
        $query = Agent::forCurrentOrganization($this->user)
            ->where('enabled', true);

        if (! empty($request['search'])) {
            $search = $request['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $agents = $query->with('skills', 'repos')->get();

        return json_encode($agents->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Optional search term to filter agents by name or description.'),
        ];
    }
}
