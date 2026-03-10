<?php

use App\Events\PlanLimitReached;
use App\Events\PlanStepPartial;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\Workspace;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
});

describe('PlanStep partial status', function () {
    it('tracks partial state on the model', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
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
            'workspace_id' => $this->workspace->id,
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

describe('PlanStepPartial event', function () {
    it('broadcasts on the organization channel', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
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
            'workspace_id' => $this->workspace->id,
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
