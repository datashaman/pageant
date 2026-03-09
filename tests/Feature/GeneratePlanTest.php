<?php

use App\Contracts\ExecutionDriver;
use App\Events\WorkItemCreated;
use App\Jobs\GeneratePlan;
use App\Listeners\HandleWorkItemCreated;
use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\WorktreeManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
        'installation_id' => 12345,
    ]);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

describe('HandleWorkItemCreated dispatches GeneratePlan', function () {
    it('dispatches GeneratePlan job when a work item is created', function () {
        Queue::fake();

        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'events' => ['work_item_created'],
            'tools' => ['read_file', 'glob', 'grep'],
        ]);
        $this->repo->agents()->attach($agent);

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
            'title' => 'Fix login bug',
            'description' => 'Users cannot log in',
        ]);

        $listener = new HandleWorkItemCreated;
        $listener->handle(new WorkItemCreated($workItem, 'acme/widgets', 12345));

        Queue::assertPushed(GeneratePlan::class, function ($job) use ($workItem) {
            return $job->workItem->id === $workItem->id
                && $job->repoFullName === 'acme/widgets';
        });
    });

    it('dispatches GeneratePlan even when no agents subscribe to work_item_created event', function () {
        Queue::fake();

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
        ]);

        $listener = new HandleWorkItemCreated;
        $listener->handle(new WorkItemCreated($workItem, 'acme/widgets', 12345));

        Queue::assertPushed(GeneratePlan::class);
    });
});

describe('GeneratePlan job', function () {
    it('skips when work item already has an active plan', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
        ]);

        Plan::factory()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $workItem->id,
            'status' => 'pending',
        ]);

        $job = new GeneratePlan($workItem, 'acme/widgets');
        $job->handle(app(WorktreeManager::class));

        expect(Plan::where('work_item_id', $workItem->id)->count())->toBe(1);
    });

    it('skips when organization has no planning agent configured', function () {
        Log::spy();

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
        ]);

        $job = new GeneratePlan($workItem, 'acme/widgets');
        $job->handle(app(WorktreeManager::class));

        expect(Plan::where('work_item_id', $workItem->id)->count())->toBe(0);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message) => str_contains($message, 'no planning agent configured'))
            ->once();
    });

    it('skips when worktree provisioning fails', function () {
        Log::spy();

        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'enabled' => true,
            'tools' => ['read_file', 'glob'],
        ]);
        $this->organization->update(['planning_agent_id' => $agent->id]);

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
        ]);

        $mockManager = Mockery::mock(WorktreeManager::class);
        $mockManager->shouldReceive('createDriver')
            ->once()
            ->andThrow(new RuntimeException('Clone failed'));

        $job = new GeneratePlan($workItem, 'acme/widgets');
        $job->handle($mockManager);

        expect(Plan::where('work_item_id', $workItem->id)->count())->toBe(0);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'worktree provisioning failed'))
            ->once();
    });

    it('logs error and creates no plan when agent execution fails', function () {
        Log::spy();

        $agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
            'enabled' => true,
            'tools' => ['read_file', 'glob'],
        ]);
        $this->organization->update(['planning_agent_id' => $agent->id]);

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
        ]);

        $mockDriver = Mockery::mock(ExecutionDriver::class);
        $mockManager = Mockery::mock(WorktreeManager::class);
        $mockManager->shouldReceive('createDriver')
            ->once()
            ->andReturn($mockDriver);

        $job = new GeneratePlan($workItem, 'acme/widgets');
        $job->handle($mockManager);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message) => str_contains($message, 'agent execution failed'))
            ->once();

        expect(Plan::where('work_item_id', $workItem->id)->count())->toBe(0);
    });

    it('has the correct unique id', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $job = new GeneratePlan($workItem, 'acme/widgets');

        expect($job->uniqueId())->toBe("generate-plan:{$workItem->id}");
    });
});
