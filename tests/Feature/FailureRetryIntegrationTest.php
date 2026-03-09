<?php

use App\Enums\FailureCategory;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\WorkItem;
use App\Services\FailureClassifier;
use App\Services\RetryPolicy;
use App\Services\WorkItemOrchestrator;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
});

describe('PlanStep failure_category column', function () {
    it('stores failure category on a failed step', function () {
        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $step = PlanStep::factory()->failedWithCategory(FailureCategory::RateLimit, 4)->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        $step->refresh();

        expect($step->failure_category)->toBe(FailureCategory::RateLimit)
            ->and($step->retry_attempts)->toBe(4);
    });

    it('stores null failure category for successful steps', function () {
        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $step = PlanStep::factory()->completed()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        $step->refresh();

        expect($step->failure_category)->toBeNull()
            ->and($step->retry_attempts)->toBe(0);
    });
});

describe('FailureClassifier integration', function () {
    it('classifies real HTTP exceptions correctly', function () {
        $classifier = new FailureClassifier;

        $timeoutException = new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        expect($classifier->classify($timeoutException))->toBe(FailureCategory::Timeout);

        $unknownException = new \InvalidArgumentException('Bad argument');
        expect($classifier->classify($unknownException))->toBe(FailureCategory::Unknown);
    });
});

describe('RetryPolicy integration', function () {
    it('provides different policies for different categories', function () {
        $rateLimitPolicy = RetryPolicy::forCategory(FailureCategory::RateLimit);
        $unknownPolicy = RetryPolicy::forCategory(FailureCategory::Unknown);

        expect($rateLimitPolicy->maxAttempts)->toBeGreaterThan($unknownPolicy->maxAttempts);
    });
});

describe('WorkItemOrchestrator retryCapInstructions', function () {
    it('generates retry cap instructions for agent prompts', function () {
        $orchestrator = app(WorkItemOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'retryCapInstructions');

        $instructions = $method->invoke($orchestrator);

        expect($instructions)->toContain('Retry Policies')
            ->and($instructions)->toContain('rate limit')
            ->and($instructions)->toContain('timeout')
            ->and($instructions)->toContain('github api')
            ->and($instructions)->toContain('tool error')
            ->and($instructions)->toContain('model error')
            ->and($instructions)->toContain('unknown');
    });
});
