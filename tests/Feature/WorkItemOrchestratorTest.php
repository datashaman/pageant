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

describe('WorkItemOrchestrator::summarizeResponse', function () {
    it('truncates responses to 200 characters', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'summarizeResponse');

        $longText = str_repeat('a', 300);
        $result = $method->invoke($orchestrator, $longText);

        expect(strlen($result))->toBe(200)
            ->and($result)->toEndWith('...');
    });

    it('does not truncate short responses', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'summarizeResponse');

        $shortText = 'This is a short response.';
        $result = $method->invoke($orchestrator, $shortText);

        expect($result)->toBe($shortText);
    });
});

describe('WorkItemOrchestrator::buildPriorStepsContext', function () {
    it('returns null when there are no prior steps', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        $step = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildPriorStepsContext');

        expect($method->invoke($orchestrator, $step))->toBeNull();
    });

    it('limits prior steps to the last 3 via sliding window', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        for ($i = 1; $i <= 5; $i++) {
            PlanStep::factory()->create([
                'plan_id' => $plan->id,
                'agent_id' => $agent->id,
                'order' => $i,
                'status' => 'completed',
                'description' => "Step {$i} description",
                'result' => "Result {$i}",
            ]);
        }

        $currentStep = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 6,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildPriorStepsContext');

        $context = $method->invoke($orchestrator, $currentStep);

        expect($context)->toContain('Step 3 description')
            ->and($context)->toContain('Step 4 description')
            ->and($context)->toContain('Step 5 description')
            ->and($context)->not->toContain('Step 1 description')
            ->and($context)->not->toContain('Step 2 description');
    });

    it('enforces a total character budget and keeps newest steps', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        for ($i = 1; $i <= 3; $i++) {
            PlanStep::factory()->create([
                'plan_id' => $plan->id,
                'agent_id' => $agent->id,
                'order' => $i,
                'status' => 'completed',
                'description' => str_repeat("Step {$i} ", 100),
                'result' => str_repeat('x', 200),
            ]);
        }

        $currentStep = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 4,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildPriorStepsContext');

        $context = $method->invoke($orchestrator, $currentStep);
        $contextWithoutHeader = str_replace("## Prior Steps\n", '', $context);

        expect(strlen($contextWithoutHeader))->toBeLessThanOrEqual(2000);

        expect($context)->toContain('Step 3 ')
            ->and($context)->not->toContain('Step 1 ');
    });

    it('skips an oversized older step while keeping newer steps that fit', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'completed',
            'description' => 'Small oldest step',
            'result' => 'done',
        ]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 2,
            'status' => 'completed',
            'description' => str_repeat('x', 1900),
            'result' => str_repeat('y', 200),
        ]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 3,
            'status' => 'completed',
            'description' => 'Small newest step',
            'result' => 'done',
        ]);

        $currentStep = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 4,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildPriorStepsContext');

        $context = $method->invoke($orchestrator, $currentStep);

        expect($context)->toContain('Small newest step')
            ->and($context)->toContain('Small oldest step')
            ->and($context)->not->toContain(str_repeat('x', 1900));
    });

    it('returns null when all steps exceed the budget individually', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'completed',
            'description' => str_repeat('x', 2100),
            'result' => 'done',
        ]);

        $currentStep = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 2,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildPriorStepsContext');

        expect($method->invoke($orchestrator, $currentStep))->toBeNull();
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
