<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\WorkItem;
use App\Services\PlanStepValidator;
use Laravel\Ai\AnonymousAgent;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->plan = Plan::factory()->create([
        'organization_id' => $this->organization->id,
        'work_item_id' => $this->workItem->id,
    ]);
    $this->agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'provider' => 'anthropic',
        'secondary_model' => 'cheapest',
    ]);
    $this->step = PlanStep::factory()->create([
        'plan_id' => $this->plan->id,
        'agent_id' => $this->agent->id,
        'order' => 1,
        'description' => 'Analyze the codebase structure.',
    ]);
});

it('returns passed when the model responds PASSED', function () {
    AnonymousAgent::fake([
        "PASSED\nThe step correctly analyzed the codebase structure.",
    ]);

    $validator = app(PlanStepValidator::class);
    $result = $validator->validate(
        $this->step,
        'Found 15 PHP files in app/Models directory.',
        'Plan: Fix authentication bug',
    );

    expect($result['status'])->toBe('passed');
    expect($result['reason'])->toContain('correctly analyzed');
});

it('returns failed when the model responds FAILED', function () {
    AnonymousAgent::fake([
        "FAILED\nThe step did not complete the analysis.",
    ]);

    $validator = app(PlanStepValidator::class);
    $result = $validator->validate(
        $this->step,
        'Error: could not read directory.',
        'Plan: Fix authentication bug',
    );

    expect($result['status'])->toBe('failed');
    expect($result['reason'])->toContain('did not complete');
});

it('returns uncertain when the model responds UNCERTAIN', function () {
    AnonymousAgent::fake([
        "UNCERTAIN\nThe output is ambiguous.",
    ]);

    $validator = app(PlanStepValidator::class);
    $result = $validator->validate(
        $this->step,
        'Partial results found.',
        'Plan: Fix authentication bug',
    );

    expect($result['status'])->toBe('uncertain');
    expect($result['reason'])->toContain('ambiguous');
});

it('returns uncertain when the model call fails', function () {
    AnonymousAgent::fake(function () {
        throw new \RuntimeException('API unavailable');
    });

    $validator = app(PlanStepValidator::class);
    $result = $validator->validate(
        $this->step,
        'Some output.',
        'Plan: Fix authentication bug',
    );

    expect($result['status'])->toBe('uncertain');
    expect($result['reason'])->toBe('Validation could not be performed.');
});

it('constructs the prompt with step and plan context', function () {
    AnonymousAgent::fake([
        "PASSED\nAll good.",
    ]);

    $validator = app(PlanStepValidator::class);
    $validator->validate(
        $this->step,
        'Analysis complete.',
        'Plan: Fix authentication bug',
    );

    AnonymousAgent::assertPrompted(function ($prompt) {
        return $prompt->contains('Analyze the codebase structure')
            && $prompt->contains('Fix authentication bug');
    });
});
