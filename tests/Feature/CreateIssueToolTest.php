<?php

use App\Ai\Tools\CreateIssueTool;
use App\Events\WorkItemCreated;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Tools\Request;
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

it('creates an issue and automatically creates a linked work item', function () {
    Event::fake([WorkItemCreated::class]);

    $github = $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createIssue')
            ->once()
            ->andReturn([
                'number' => 99,
                'title' => 'Fix the widget',
                'body' => 'It is broken',
                'state' => 'open',
                'html_url' => 'https://github.com/acme/widgets/issues/99',
            ]);
    });

    $tool = new CreateIssueTool($github, $this->installation, 'acme/widgets');

    $result = $tool->handle(new Request(['title' => 'Fix the widget', 'body' => 'It is broken']));

    $decoded = json_decode($result, true);

    expect($decoded)
        ->toHaveKey('issue')
        ->toHaveKey('work_item');

    expect($decoded['issue']['number'])->toBe(99);
    expect($decoded['work_item']['title'])->toBe('Fix the widget');
    expect($decoded['work_item']['source_reference'])->toBe('acme/widgets#99');

    $this->assertDatabaseHas('work_items', [
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#99',
        'title' => 'Fix the widget',
    ]);

    Event::assertDispatched(WorkItemCreated::class);
});

it('skips work item creation when skip_work_item is true', function () {
    Event::fake([WorkItemCreated::class]);

    $github = $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createIssue')
            ->once()
            ->andReturn([
                'number' => 100,
                'title' => 'Quick fix',
                'state' => 'open',
                'html_url' => 'https://github.com/acme/widgets/issues/100',
            ]);
    });

    $tool = new CreateIssueTool($github, $this->installation, 'acme/widgets');

    $result = $tool->handle(new Request(['title' => 'Quick fix', 'skip_work_item' => true]));

    $decoded = json_decode($result, true);

    expect($decoded)
        ->toHaveKey('issue')
        ->not->toHaveKey('work_item');

    $this->assertDatabaseMissing('work_items', [
        'source_reference' => 'acme/widgets#100',
    ]);

    Event::assertNotDispatched(WorkItemCreated::class);
});

it('does not duplicate work item when issue already has one', function () {
    Event::fake([WorkItemCreated::class]);

    WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#101',
        'title' => 'Existing item',
    ]);

    $github = $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createIssue')
            ->once()
            ->andReturn([
                'number' => 101,
                'title' => 'Existing item',
                'state' => 'open',
                'html_url' => 'https://github.com/acme/widgets/issues/101',
            ]);
    });

    $tool = new CreateIssueTool($github, $this->installation, 'acme/widgets');

    $result = $tool->handle(new Request(['title' => 'Existing item']));

    $decoded = json_decode($result, true);

    expect($decoded)->toHaveKey('work_item');

    // Should not dispatch event for existing work item
    Event::assertNotDispatched(WorkItemCreated::class);

    // Should only have one work item with that reference
    expect(WorkItem::where('source_reference', 'acme/widgets#101')->count())->toBe(1);
});

it('resolves repo and installation when not provided to constructor', function () {
    Event::fake([WorkItemCreated::class]);

    $github = $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createIssue')
            ->once()
            ->andReturn([
                'number' => 102,
                'title' => 'No-context issue',
                'state' => 'open',
                'html_url' => 'https://github.com/acme/widgets/issues/102',
            ]);
    });

    $tool = new CreateIssueTool($github);

    $result = $tool->handle(new Request(['repo' => 'acme/widgets', 'title' => 'No-context issue']));

    $decoded = json_decode($result, true);

    expect($decoded)
        ->toHaveKey('issue')
        ->toHaveKey('work_item');

    expect($decoded['issue']['number'])->toBe(102);
});
