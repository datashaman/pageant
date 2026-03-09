<?php

use App\Events\PlanCompleted;
use App\Events\PlanFailed;
use App\Listeners\StoreAgentMemory;
use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\AgentMemoryService;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->service = app(AgentMemoryService::class);
});

describe('AgentMemoryService::storeFromPlan', function () {
    it('stores a learning memory from a completed plan', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Add user authentication',
        ]);

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $workItem->id,
            'status' => 'completed',
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'completed',
            'description' => 'Create auth controller',
        ]);

        $memory = $this->service->storeFromPlan($plan);

        expect($memory)->toBeInstanceOf(AgentMemory::class)
            ->and($memory->type)->toBe('learning')
            ->and($memory->organization_id)->toBe($this->organization->id)
            ->and($memory->summary)->toContain('Successfully completed')
            ->and($memory->summary)->toContain('Add user authentication')
            ->and($memory->content)->toContain('Add user authentication')
            ->and($memory->content)->toContain('[completed] Create auth controller')
            ->and($memory->importance)->toBeGreaterThanOrEqual(1)
            ->and($memory->importance)->toBeLessThanOrEqual(10)
            ->and($memory->metadata)->toHaveKey('plan_id')
            ->and($memory->metadata)->toHaveKey('work_item_id');
    });

    it('stores a failure memory from a failed plan', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Fix broken migration',
        ]);

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $workItem->id,
            'status' => 'failed',
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'failed',
            'description' => 'Run migration',
        ]);

        $memory = $this->service->storeFromPlan($plan);

        expect($memory->type)->toBe('failure')
            ->and($memory->summary)->toContain('Failed')
            ->and($memory->importance)->toBeGreaterThanOrEqual(7);
    });

    it('links memory to a repo when source reference matches', function () {
        $repo = Repo::factory()->create([
            'organization_id' => $this->organization->id,
            'source_reference' => 'owner/repo',
        ]);

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source_reference' => 'owner/repo#42',
        ]);

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $workItem->id,
            'status' => 'completed',
        ]);

        $memory = $this->service->storeFromPlan($plan);

        expect($memory->repo_id)->toBe($repo->id);
    });

    it('assigns higher importance to failures with multiple failed steps', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $workItem->id,
            'status' => 'failed',
        ]);

        $agent = Agent::factory()->create(['organization_id' => $this->organization->id]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'status' => 'failed',
        ]);

        PlanStep::factory()->create([
            'plan_id' => $plan->id,
            'agent_id' => $agent->id,
            'order' => 2,
            'status' => 'failed',
        ]);

        $memory = $this->service->storeFromPlan($plan);

        expect($memory->importance)->toBeGreaterThanOrEqual(9);
    });
});

describe('AgentMemoryService::retrieve', function () {
    it('retrieves memories for an organization', function () {
        AgentMemory::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $otherOrg = Organization::factory()->create();
        AgentMemory::factory()->count(2)->create([
            'organization_id' => $otherOrg->id,
        ]);

        $memories = $this->service->retrieve($this->organization->id);

        expect($memories)->toHaveCount(3);
    });

    it('filters by type when specified', function () {
        AgentMemory::factory()->learning()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);

        AgentMemory::factory()->failure()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $learnings = $this->service->retrieve($this->organization->id, type: 'learning');
        $failures = $this->service->retrieve($this->organization->id, type: 'failure');

        expect($learnings)->toHaveCount(2)
            ->and($failures)->toHaveCount(3);
    });

    it('includes org-wide memories when filtering by repo', function () {
        $repo = Repo::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
            'repo_id' => $repo->id,
            'summary' => 'Repo-specific memory',
        ]);

        AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
            'repo_id' => null,
            'summary' => 'Org-wide memory',
        ]);

        $memories = $this->service->retrieve($this->organization->id, $repo->id);

        expect($memories)->toHaveCount(2);
    });

    it('respects token budget by skipping large memories', function () {
        AgentMemory::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'summary' => str_repeat('x', 500),
            'importance' => 5,
        ]);

        $memories = $this->service->retrieve($this->organization->id);

        $totalChars = $memories->sum(fn ($m) => mb_strlen($m->summary));

        expect($totalChars)->toBeLessThanOrEqual(500 * 4);
    });

    it('prioritizes high-importance memories', function () {
        AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
            'importance' => 2,
            'summary' => 'Low importance',
        ]);

        AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
            'importance' => 10,
            'summary' => 'High importance',
        ]);

        $memories = $this->service->retrieve($this->organization->id);

        expect($memories->first()->summary)->toBe('High importance');
    });
});

describe('AgentMemoryService::buildContext', function () {
    it('returns null when no memories exist', function () {
        $context = $this->service->buildContext($this->organization->id);

        expect($context)->toBeNull();
    });

    it('returns formatted context string with memories', function () {
        AgentMemory::factory()->learning()->create([
            'organization_id' => $this->organization->id,
            'summary' => 'Tests should use factories',
        ]);

        AgentMemory::factory()->failure()->create([
            'organization_id' => $this->organization->id,
            'summary' => 'Migration failed due to missing column',
        ]);

        $context = $this->service->buildContext($this->organization->id);

        expect($context)->toContain('## Prior Learnings')
            ->and($context)->toContain('[LEARNING] Tests should use factories')
            ->and($context)->toContain('[FAILURE] Migration failed due to missing column');
    });
});

describe('StoreAgentMemory listener', function () {
    it('stores memory when plan completes', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $workItem->id,
            'status' => 'completed',
        ]);

        $listener = app(StoreAgentMemory::class);
        $listener->handle(new PlanCompleted($plan));

        expect(AgentMemory::where('organization_id', $this->organization->id)->count())->toBe(1);
    });

    it('stores memory when plan fails', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $plan = Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $workItem->id,
            'status' => 'failed',
        ]);

        $listener = app(StoreAgentMemory::class);
        $listener->handle(new PlanFailed($plan));

        $memory = AgentMemory::where('organization_id', $this->organization->id)->first();

        expect($memory->type)->toBe('failure');
    });
});

describe('AgentMemory model', function () {
    it('belongs to an organization', function () {
        $memory = AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        expect($memory->organization->id)->toBe($this->organization->id);
    });

    it('optionally belongs to a repo', function () {
        $repo = Repo::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $memory = AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
            'repo_id' => $repo->id,
        ]);

        expect($memory->repo->id)->toBe($repo->id);
    });

    it('optionally belongs to an agent', function () {
        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $memory = AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
            'agent_id' => $agent->id,
        ]);

        expect($memory->agent->id)->toBe($agent->id);
    });

    it('casts metadata as array', function () {
        $memory = AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
            'metadata' => ['key' => 'value'],
        ]);

        $memory->refresh();

        expect($memory->metadata)->toBeArray()
            ->and($memory->metadata['key'])->toBe('value');
    });
});

describe('AgentMemory factory', function () {
    it('creates valid memories with default state', function () {
        $memory = AgentMemory::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        expect($memory->exists)->toBeTrue()
            ->and($memory->type)->toBeIn(['learning', 'entity', 'pattern', 'failure'])
            ->and($memory->importance)->toBeGreaterThanOrEqual(1)
            ->and($memory->importance)->toBeLessThanOrEqual(10);
    });

    it('supports type-specific states', function () {
        $learning = AgentMemory::factory()->learning()->make();
        $failure = AgentMemory::factory()->failure()->make();
        $pattern = AgentMemory::factory()->pattern()->make();
        $entity = AgentMemory::factory()->entity()->make();

        expect($learning->type)->toBe('learning')
            ->and($failure->type)->toBe('failure')
            ->and($pattern->type)->toBe('pattern')
            ->and($entity->type)->toBe('entity');
    });

    it('supports importance states', function () {
        $high = AgentMemory::factory()->highImportance()->make();
        $low = AgentMemory::factory()->lowImportance()->make();

        expect($high->importance)->toBeGreaterThanOrEqual(8)
            ->and($low->importance)->toBeLessThanOrEqual(3);
    });
});
