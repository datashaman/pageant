<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\Skill;
use App\Models\WorkItem;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
});

describe('Plan Model', function () {
    it('belongs to a work item', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        expect($plan->workItem->id)->toBe($this->workItem->id);
    });

    it('has many steps ordered by order', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent1 = Agent::factory()->create(['organization_id' => $this->organization->id]);
        $agent2 = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->create(['plan_id' => $plan->id, 'agent_id' => $agent2->id, 'order' => 2]);
        PlanStep::factory()->create(['plan_id' => $plan->id, 'agent_id' => $agent1->id, 'order' => 1]);

        $steps = $plan->steps;

        expect($steps)->toHaveCount(2);
        expect($steps[0]->order)->toBe(1);
        expect($steps[1]->order)->toBe(2);
    });

    it('tracks status correctly', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'pending',
        ]);

        expect($plan->isPending())->toBeTrue();
        expect($plan->isApproved())->toBeFalse();
        expect($plan->isRunning())->toBeFalse();
        expect($plan->isCompleted())->toBeFalse();
        expect($plan->isFailed())->toBeFalse();
    });
});

describe('WorkItem Plans', function () {
    it('has many plans', function () {
        Plan::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        expect($this->workItem->plans)->toHaveCount(2);
    });

    it('returns active plan', function () {
        Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'completed',
        ]);

        $activePlan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'pending',
        ]);

        expect($this->workItem->activePlan()->id)->toBe($activePlan->id);
    });

    it('returns null when no active plan', function () {
        Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
            'status' => 'completed',
        ]);

        expect($this->workItem->activePlan())->toBeNull();
    });
});

describe('Skill Wiring', function () {
    it('merges skill context into agent instructions', function () {
        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'description' => 'I am a code agent.',
        ]);

        $skill = Skill::factory()->create([
            'organization_id' => $this->organization->id,
            'context' => 'Always write tests using Pest.',
            'enabled' => true,
        ]);

        $agent->skills()->attach($skill);

        $webhookAgent = new \App\Ai\Agents\GitHubWebhookAgent($agent, 'owner/repo');

        expect($webhookAgent->instructions())->toContain('I am a code agent.');
        expect($webhookAgent->instructions())->toContain('Always write tests using Pest.');
        expect($webhookAgent->instructions())->toContain('## Skills');
    });

    it('excludes disabled skills from instructions', function () {
        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'description' => 'I am a code agent.',
        ]);

        $skill = Skill::factory()->create([
            'organization_id' => $this->organization->id,
            'context' => 'This should not appear.',
            'enabled' => false,
        ]);

        $agent->skills()->attach($skill);

        $webhookAgent = new \App\Ai\Agents\GitHubWebhookAgent($agent, 'owner/repo');

        expect($webhookAgent->instructions())->not->toContain('This should not appear.');
        expect($webhookAgent->instructions())->not->toContain('## Skills');
    });
});

describe('PlanStep Model', function () {
    it('belongs to a plan and agent', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        $step = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'description' => 'Analyze the codebase.',
        ]);

        expect($step->plan->id)->toBe($plan->id);
        expect($step->agent->id)->toBe($agent->id);
    });

    it('stores depends_on as array', function () {
        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        $step = PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'depends_on' => ['step-1-uuid', 'step-2-uuid'],
        ]);

        expect($step->depends_on)->toBe(['step-1-uuid', 'step-2-uuid']);
    });
});

describe('Plan Factory States', function () {
    it('creates approved plan', function () {
        $plan = Plan::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        expect($plan->isApproved())->toBeTrue();
        expect($plan->approved_at)->not->toBeNull();
    });

    it('creates running plan', function () {
        $plan = Plan::factory()->running()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        expect($plan->isRunning())->toBeTrue();
        expect($plan->started_at)->not->toBeNull();
    });

    it('creates completed plan', function () {
        $plan = Plan::factory()->completed()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        expect($plan->isCompleted())->toBeTrue();
        expect($plan->completed_at)->not->toBeNull();
    });
});
