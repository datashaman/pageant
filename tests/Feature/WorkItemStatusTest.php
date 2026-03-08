<?php

use App\Events\GitHubIssueReceived;
use App\Listeners\SyncWorkItemStatus;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
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
    it('syncs work item status from GitHub', function () {
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

        $this->artisan('work-items:reconcile')
            ->assertSuccessful();

        expect($workItem->fresh()->status)->toBe('closed');
    });

    it('reports no work items when none exist', function () {
        WorkItem::where('source', 'github')->delete();

        $this->artisan('work-items:reconcile')
            ->expectsOutput('No GitHub-linked work items found.')
            ->assertSuccessful();
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
