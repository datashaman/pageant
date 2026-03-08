<?php

use App\Events\GitHubIssueReceived;
use App\Jobs\ReconcileWorkItemStatuses;
use App\Listeners\SyncWorkItemStatus;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

describe('SyncWorkItemStatus listener', function () {
    it('closes a work item when GitHub issue is closed', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
            'status' => 'open',
        ]);

        $event = new GitHubIssueReceived(
            action: 'closed',
            issue: ['number' => 42, 'title' => 'Test', 'body' => '', 'state' => 'closed', 'labels' => []],
            repository: ['full_name' => 'acme/widgets'],
            installationId: $this->installation->installation_id,
        );

        (new SyncWorkItemStatus)->handle($event);

        expect($workItem->fresh()->status)->toBe('closed');
    });

    it('reopens a work item when GitHub issue is reopened', function () {
        $workItem = WorkItem::factory()->closed()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
        ]);

        $event = new GitHubIssueReceived(
            action: 'reopened',
            issue: ['number' => 42, 'title' => 'Test', 'body' => '', 'state' => 'open', 'labels' => []],
            repository: ['full_name' => 'acme/widgets'],
            installationId: $this->installation->installation_id,
        );

        (new SyncWorkItemStatus)->handle($event);

        expect($workItem->fresh()->status)->toBe('open');
    });

    it('ignores events for untracked issues', function () {
        $event = new GitHubIssueReceived(
            action: 'closed',
            issue: ['number' => 999, 'title' => 'Unknown', 'body' => '', 'state' => 'closed', 'labels' => []],
            repository: ['full_name' => 'acme/widgets'],
            installationId: $this->installation->installation_id,
        );

        (new SyncWorkItemStatus)->handle($event);

        // No exception — silently ignored
        expect(true)->toBeTrue();
    });

    it('ignores non-close/reopen actions', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
            'status' => 'open',
        ]);

        $event = new GitHubIssueReceived(
            action: 'labeled',
            issue: ['number' => 42, 'title' => 'Test', 'body' => '', 'state' => 'open', 'labels' => []],
            repository: ['full_name' => 'acme/widgets'],
            installationId: $this->installation->installation_id,
        );

        (new SyncWorkItemStatus)->handle($event);

        expect($workItem->fresh()->status)->toBe('open');
    });
});

describe('work-items:reconcile command', function () {
    it('dispatches the reconciliation job', function () {
        Queue::fake();

        WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
            'status' => 'open',
        ]);

        $this->artisan('work-items:reconcile')
            ->assertSuccessful();

        Queue::assertPushed(ReconcileWorkItemStatuses::class);
    });

    it('reports no work items when none exist', function () {
        WorkItem::where('source', 'github')->delete();

        $this->artisan('work-items:reconcile')
            ->expectsOutput('No GitHub-linked work items found.')
            ->assertSuccessful();
    });
});

describe('ReconcileWorkItemStatuses job', function () {
    it('reconciles statuses for a specific organization', function () {
        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
            'status' => 'open',
        ]);

        $this->mock(GitHubService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getIssue')
                ->once()
                ->andReturn([
                    'number' => 42,
                    'state' => 'closed',
                ]);
        });

        ReconcileWorkItemStatuses::dispatchSync($this->organization);

        expect($workItem->fresh()->status)->toBe('closed');
    });

    it('skips work items from other organizations', function () {
        $otherOrg = Organization::factory()->create();
        GithubInstallation::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherWorkItem = WorkItem::factory()->create([
            'organization_id' => $otherOrg->id,
            'source' => 'github',
            'source_reference' => 'other/repo#10',
            'status' => 'open',
        ]);

        $this->mock(GitHubService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getIssue');
        });

        ReconcileWorkItemStatuses::dispatchSync($this->organization);

        expect($otherWorkItem->fresh()->status)->toBe('open');
    });
});

describe('work items index page load reconciliation', function () {
    it('reconciles statuses synchronously on page load', function () {
        $user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
        ]);
        $user->organizations()->attach($this->organization);

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
            'status' => 'open',
        ]);

        $this->mock(GitHubService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getIssue')
                ->with(Mockery::on(fn ($inst) => $inst->id === $this->installation->id), 'acme/widgets', 42)
                ->once()
                ->andReturn(['number' => 42, 'state' => 'closed']);
        });

        Livewire::actingAs($user)
            ->test('pages::work-items.index');

        expect($workItem->fresh()->status)->toBe('closed');
    });

    it('displays reconciled statuses immediately on page load', function () {
        $user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
        ]);
        $user->organizations()->attach($this->organization);

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#7',
            'status' => 'open',
            'title' => 'Stale open issue',
        ]);

        $this->mock(GitHubService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getIssue')
                ->with(Mockery::on(fn ($inst) => $inst->id === $this->installation->id), 'acme/widgets', 7)
                ->once()
                ->andReturn(['number' => 7, 'state' => 'closed']);
        });

        Livewire::actingAs($user)
            ->test('pages::work-items.index')
            ->set('statusFilter', 'closed')
            ->assertSee('Closed');
    });

    it('syncs statuses when sync button is clicked', function () {
        $user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
        ]);
        $user->organizations()->attach($this->organization);

        $workItem = WorkItem::factory()->create([
            'organization_id' => $this->organization->id,
            'source' => 'github',
            'source_reference' => 'acme/widgets#42',
            'status' => 'open',
        ]);

        $this->mock(GitHubService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getIssue')
                ->with(Mockery::on(fn ($inst) => $inst->id === $this->installation->id), 'acme/widgets', 42)
                ->andReturn(['number' => 42, 'state' => 'closed']);
        });

        Livewire::actingAs($user)
            ->test('pages::work-items.index')
            ->call('syncStatuses');

        expect($workItem->fresh()->status)->toBe('closed');
    });
});

describe('WorkItem model status', function () {
    it('defaults to open status', function () {
        $attributes = WorkItem::factory()->make([
            'organization_id' => $this->organization->id,
        ])->getAttributes();

        unset($attributes['status']);

        $workItem = WorkItem::query()->create($attributes)->fresh();

        expect($workItem->status)->toBe('open')
            ->and($workItem->isOpen())->toBeTrue()
            ->and($workItem->isClosed())->toBeFalse();
    });

    it('can be closed', function () {
        $workItem = WorkItem::factory()->closed()->create([
            'organization_id' => $this->organization->id,
        ]);

        expect($workItem->status)->toBe('closed')
            ->and($workItem->isOpen())->toBeFalse()
            ->and($workItem->isClosed())->toBeTrue();
    });
});
