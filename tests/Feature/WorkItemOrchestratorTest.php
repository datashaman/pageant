<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\WorkItem;
use App\Services\WorkItemOrchestrator;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
});

describe('WorkItemOrchestrator::cancel', function () {
    it('cancels a pending plan and skips remaining steps', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'pending',
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'pending',
        ]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 2,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $orchestrator->cancel($plan);

        $plan->refresh();

        expect($plan->status)->toBe('cancelled');
        expect($plan->completed_at)->not->toBeNull();
        expect($plan->steps->every(fn ($step) => $step->status === 'skipped'))->toBeTrue();
    });
});

describe('WorkItemOrchestrator::execute', function () {
    it('rejects non-approved plans', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);

        expect(fn () => $orchestrator->execute($plan))
            ->toThrow(InvalidArgumentException::class, 'Plan must be approved');
    });
});

describe('ToolRegistry plan tools', function () {
    it('includes plan tools in the registry', function () {
        $available = \App\Ai\ToolRegistry::available();

        expect($available)->toHaveKey('create_plan');
        expect($available)->toHaveKey('get_plan');
        expect($available)->toHaveKey('list_plans');
        expect($available)->toHaveKey('approve_plan');
        expect($available)->toHaveKey('cancel_plan');
        expect($available)->toHaveKey('list_agents');
        expect($available)->toHaveKey('list_skills');
        expect($available)->toHaveKey('attach_skill_to_agent');
    });

    it('categorizes plan tools as pageant tools', function () {
        $pageantTools = \App\Ai\ToolRegistry::pageantToolNames();

        expect($pageantTools)->toContain('create_plan');
        expect($pageantTools)->toContain('list_plans');
        expect($pageantTools)->toContain('list_agents');
        expect($pageantTools)->toContain('list_skills');
    });
});
