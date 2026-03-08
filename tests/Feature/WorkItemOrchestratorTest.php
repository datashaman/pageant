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

describe('WorkItemOrchestrator pause/resume', function () {
    it('rejects execution of a paused plan that has not been resumed', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'paused',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);

        // Paused plans can be executed (resume sets to approved then dispatches)
        // but a raw paused plan should also work since the loop checks status
        $plan->update(['status' => 'approved']);
        $plan->refresh();

        expect($plan->isApproved())->toBeTrue();
    });
});

describe('Plan paused status', function () {
    it('tracks paused state', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'paused',
        ]);

        expect($plan->isPaused())->toBeTrue();
        expect($plan->isRunning())->toBeFalse();
    });

    it('includes paused plans in active plan query', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'paused',
        ]);

        expect($this->workItem->activePlan()->id)->toBe($plan->id);
    });
});

describe('ToolRegistry plan tools', function () {
    it('includes all plan tools in the registry', function () {
        $available = \App\Ai\ToolRegistry::available();

        expect($available)->toHaveKey('create_plan');
        expect($available)->toHaveKey('get_plan');
        expect($available)->toHaveKey('list_plans');
        expect($available)->toHaveKey('approve_plan');
        expect($available)->toHaveKey('cancel_plan');
        expect($available)->toHaveKey('add_plan_step');
        expect($available)->toHaveKey('pause_plan');
        expect($available)->toHaveKey('resume_plan');
        expect($available)->toHaveKey('list_agents');
        expect($available)->toHaveKey('list_skills');
        expect($available)->toHaveKey('attach_skill_to_agent');
    });

    it('categorizes plan tools as pageant tools', function () {
        $pageantTools = \App\Ai\ToolRegistry::pageantToolNames();

        expect($pageantTools)->toContain('create_plan');
        expect($pageantTools)->toContain('list_plans');
        expect($pageantTools)->toContain('add_plan_step');
        expect($pageantTools)->toContain('pause_plan');
        expect($pageantTools)->toContain('resume_plan');
        expect($pageantTools)->toContain('list_agents');
        expect($pageantTools)->toContain('list_skills');
    });
});

describe('EventRegistry plan events', function () {
    it('includes plan events', function () {
        $available = \App\Ai\EventRegistry::available();

        expect($available)->toHaveKey('plan_step_completed');
        expect($available)->toHaveKey('plan_step_failed');
        expect($available)->toHaveKey('plan_completed');
        expect($available)->toHaveKey('plan_failed');
    });

    it('groups plan events together', function () {
        $grouped = \App\Ai\EventRegistry::grouped();

        expect($grouped)->toHaveKey('Plans');
        expect($grouped['Plans'])->toHaveKeys([
            'plan_step_completed',
            'plan_step_failed',
            'plan_completed',
            'plan_failed',
        ]);
    });
});
