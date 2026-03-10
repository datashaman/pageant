<?php

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
    $this->agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
});

describe('Plan::isResumable', function () {
    it('returns true for failed plans', function () {
        $plan = Plan::factory()->failed()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        expect($plan->isResumable())->toBeTrue();
    });

    it('returns true for paused plans', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'status' => 'paused',
        ]);

        expect($plan->isResumable())->toBeTrue();
    });

    it('returns false for pending plans', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'status' => 'pending',
        ]);

        expect($plan->isResumable())->toBeFalse();
    });

    it('returns false for running plans', function () {
        $plan = Plan::factory()->running()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        expect($plan->isResumable())->toBeFalse();
    });

    it('returns false for completed plans', function () {
        $plan = Plan::factory()->completed()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        expect($plan->isResumable())->toBeFalse();
    });
});

describe('Plan::resetForResume', function () {
    it('resets failed and skipped steps to pending', function () {
        $plan = Plan::factory()->failed()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        PlanStep::factory()->completed()->create([
            'plan_id' => $plan->id,
            'agent_id' => $this->agent->id,
            'order' => 1,
        ]);

        PlanStep::factory()->failed()->create([
            'plan_id' => $plan->id,
            'agent_id' => $this->agent->id,
            'order' => 2,
        ]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $this->agent->id,
            'order' => 3,
            'status' => 'skipped',
        ]);

        $plan->resetForResume();

        $steps = $plan->steps()->orderBy('order')->get();

        expect($steps[0]->status)->toBe('completed')
            ->and($steps[1]->status)->toBe('pending')
            ->and($steps[1]->started_at)->toBeNull()
            ->and($steps[1]->completed_at)->toBeNull()
            ->and($steps[1]->result)->toBeNull()
            ->and($steps[2]->status)->toBe('pending');
    });

    it('preserves completed steps', function () {
        $plan = Plan::factory()->failed()->create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $completedStep = PlanStep::factory()->completed()->create([
            'plan_id' => $plan->id,
            'agent_id' => $this->agent->id,
            'order' => 1,
            'result' => 'Step 1 completed successfully.',
        ]);

        $plan->resetForResume();

        $completedStep->refresh();

        expect($completedStep->status)->toBe('completed')
            ->and($completedStep->result)->toBe('Step 1 completed successfully.')
            ->and($completedStep->started_at)->not->toBeNull()
            ->and($completedStep->completed_at)->not->toBeNull();
    });
});
