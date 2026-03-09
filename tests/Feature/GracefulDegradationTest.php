<?php

use App\Events\PlanLimitReached;
use App\Events\PlanStepPartial;
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

describe('PlanStep partial status', function () {
    it('tracks partial state on the model', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        $step = PlanStep::factory()->partial()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        expect($step->isPartial())->toBeTrue()
            ->and($step->isFailed())->toBeFalse()
            ->and($step->isCompleted())->toBeFalse()
            ->and($step->progress_summary)->not->toBeNull();
    });

    it('stores progress_summary and turns_used fields', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        $step = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'partial',
            'progress_summary' => 'Completed 3 of 5 subtasks',
            'turns_used' => 15,
        ]);

        $step->refresh();

        expect($step->progress_summary)->toBe('Completed 3 of 5 subtasks')
            ->and($step->turns_used)->toBe(15);
    });
});

describe('WorkItemOrchestrator turn limit warning', function () {
    it('detects when approaching step limit at 80%', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'isApproachingStepLimit');

        expect($method->invoke($orchestrator, 4, 5))->toBeTrue()
            ->and($method->invoke($orchestrator, 5, 5))->toBeTrue()
            ->and($method->invoke($orchestrator, 3, 5))->toBeFalse()
            ->and($method->invoke($orchestrator, 1, 5))->toBeFalse();
    });

    it('handles zero total steps gracefully', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'isApproachingStepLimit');

        expect($method->invoke($orchestrator, 1, 0))->toBeFalse();
    });

    it('includes turn limit info in the warning when agent has max_turns', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildTurnLimitWarning');

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'max_turns' => 20,
        ]);

        $step = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        $warning = $method->invoke($orchestrator, $step, false);

        expect($warning)->toContain('maximum of 20 tool-calling turns');
    });

    it('includes approaching limit warning when flag is true', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildTurnLimitWarning');

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'max_turns' => 10,
        ]);

        $step = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        $warning = $method->invoke($orchestrator, $step, true);

        expect($warning)->toContain('approaching the plan\'s step limit')
            ->and($warning)->toContain('Summarize your progress');
    });

    it('returns null when no limits apply', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildTurnLimitWarning');

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'max_turns' => 0,
        ]);

        $step = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        $warning = $method->invoke($orchestrator, $step, false);

        expect($warning)->toBeNull();
    });
});

describe('WorkItemOrchestrator timeout detection', function () {
    it('detects timeout exceptions', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'isTimeoutException');

        expect($method->invoke($orchestrator, new \RuntimeException('Connection timed out')))->toBeTrue()
            ->and($method->invoke($orchestrator, new \RuntimeException('Request timeout')))->toBeTrue()
            ->and($method->invoke($orchestrator, new \RuntimeException('max steps exceeded')))->toBeTrue()
            ->and($method->invoke($orchestrator, new \RuntimeException('maximum number of steps')))->toBeTrue()
            ->and($method->invoke($orchestrator, new \Illuminate\Http\Client\ConnectionException('Connection failed')))->toBeTrue()
            ->and($method->invoke($orchestrator, new \RuntimeException('Something else failed')))->toBeFalse();
    });
});

describe('WorkItemOrchestrator progress summary', function () {
    it('builds a summary of completed, partial, and remaining steps', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->completed()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'description' => 'Set up project',
            'result' => 'Project initialized',
        ]);

        PlanStep::factory()->partial()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 2,
            'description' => 'Implement feature',
            'progress_summary' => 'Half of the feature done',
        ]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 3,
            'description' => 'Write tests',
            'status' => 'skipped',
        ]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 4,
            'description' => 'Deploy',
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildProgressSummary');

        $summary = $method->invoke($orchestrator, $plan);

        expect($summary)->toContain('## Completed Steps')
            ->and($summary)->toContain('Set up project')
            ->and($summary)->toContain('Project initialized')
            ->and($summary)->toContain('## Partially Completed Steps')
            ->and($summary)->toContain('Implement feature')
            ->and($summary)->toContain('Half of the feature done')
            ->and($summary)->toContain('## Remaining Steps')
            ->and($summary)->toContain('Write tests')
            ->and($summary)->toContain('Deploy');
    });
});

describe('WorkItemOrchestrator prior steps with partial status', function () {
    it('includes partial steps in prior context', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->partial()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'description' => 'Partially done task',
            'result' => 'Got halfway',
        ]);

        $currentStep = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 2,
            'status' => 'pending',
        ]);

        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'buildPriorStepsContext');

        $context = $method->invoke($orchestrator, $currentStep);

        expect($context)->toContain('[PARTIAL]')
            ->and($context)->toContain('Partially done task');
    });
});

describe('PlanStepPartial event', function () {
    it('broadcasts on the organization channel', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        $step = PlanStep::factory()->partial()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'progress_summary' => 'Some progress made',
            'turns_used' => 8,
        ]);

        $event = new PlanStepPartial($step);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('private-organization.'.$this->organization->id);

        $data = $event->broadcastWith();
        expect($data['status'])->toBe('partial')
            ->and($data['progress_summary'])->toBe('Some progress made')
            ->and($data['turns_used'])->toBe(8);
    });
});

describe('PlanLimitReached event', function () {
    it('broadcasts on the organization channel with progress summary', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $progressSummary = "## Completed Steps\n- Step 1: Done";

        $event = new PlanLimitReached($plan, $progressSummary);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('private-organization.'.$this->organization->id);

        $data = $event->broadcastWith();
        expect($data['plan_id'])->toBe($plan->id)
            ->and($data['progress_summary'])->toBe($progressSummary);
    });
});

describe('EventRegistry includes new plan events', function () {
    it('includes plan_step_partial event', function () {
        $available = \App\Ai\EventRegistry::available();

        expect($available)->toHaveKey('plan_step_partial');
        expect($available)->toHaveKey('plan_limit_reached');
    });

    it('groups new events under Plans', function () {
        $grouped = \App\Ai\EventRegistry::grouped();

        expect($grouped['Plans'])->toHaveKey('plan_step_partial')
            ->and($grouped['Plans'])->toHaveKey('plan_limit_reached');
    });
});
