<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\Organization;

class OrganizationObserver
{
    public function created(Organization $organization): void
    {
        if ($organization->planning_agent_id) {
            return;
        }

        $agent = Agent::create([
            'organization_id' => $organization->id,
            'name' => 'Planning Agent',
            'description' => 'Explores the codebase and generates execution plans for work items. Automatically assigned as the default planning agent.',
            'enabled' => true,
            'tools' => [
                'read_file',
                'glob',
                'grep',
                'list_directory',
                'git_log',
                'git_diff',
                'git_status',
                'create_plan',
                'add_plan_step',
            ],
            'events' => [],
            'provider' => 'anthropic',
            'model' => 'inherit',
            'permission_mode' => 'full',
            'max_turns' => 20,
            'background' => false,
            'isolation' => 'worktree',
        ]);

        $organization->update(['planning_agent_id' => $agent->id]);
    }
}
