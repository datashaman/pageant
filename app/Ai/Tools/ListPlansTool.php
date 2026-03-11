<?php

namespace App\Ai\Tools;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListPlansTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'List plans, optionally filtered by workspace or status.';
    }

    public function handle(Request $request): string
    {
        $query = Plan::forCurrentOrganization($this->user)
            ->with('steps.agent', 'workspace');

        if (! empty($request['workspace_id'])) {
            $query->where('workspace_id', $request['workspace_id']);
        }

        if (! empty($request['status'])) {
            $query->where('status', $request['status']);
        }

        $plans = $query->latest()->limit(20)->get();

        return json_encode($plans->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()
                ->description('Filter by workspace.'),
            'status' => $schema->string()
                ->description('Filter by status: pending, approved, running, completed, failed, cancelled.'),
        ];
    }
}
