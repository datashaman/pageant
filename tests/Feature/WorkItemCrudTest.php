<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\GitHubService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->installation = GithubInstallation::factory()->for($this->organization)->create();
    $this->repo = Repo::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/my-repo',
    ]);
    $this->project = Project::factory()->for($this->organization)->create();
    $this->workItem = WorkItem::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/my-repo#1',
        'source_url' => 'https://github.com/org/my-repo/issues/1',
    ]);

    $mock = Mockery::mock(GitHubService::class);
    $mock->shouldReceive('listIssues')->andReturn([]);
    app()->instance(GitHubService::class, $mock);
});

it('shows the work items index page', function () {
    $this->actingAs($this->user)
        ->get(route('work-items.index'))
        ->assertOk()
        ->assertSee($this->workItem->title);
});

it('can track a github issue', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->set('showImportModal', true)
        ->set('selectedRepoId', $this->repo->id)
        ->call('trackIssue', 42, 'Fix the bug', 'https://github.com/org/my-repo/issues/42');

    $this->assertDatabaseHas('work_items', [
        'title' => 'Fix the bug',
        'source' => 'github',
        'source_reference' => 'org/my-repo#42',
        'source_url' => 'https://github.com/org/my-repo/issues/42',
        'organization_id' => $this->organization->id,
    ]);
});

it('does not duplicate an already tracked issue', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->set('showImportModal', true)
        ->set('selectedRepoId', $this->repo->id)
        ->call('trackIssue', 1, 'Existing issue', 'https://github.com/org/my-repo/issues/1');

    expect(WorkItem::query()->where('source_reference', 'org/my-repo#1')->count())->toBe(1);
});

it('can untrack an issue', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->set('showImportModal', true)
        ->set('selectedRepoId', $this->repo->id)
        ->call('untrackIssue', 'org/my-repo#1');

    $this->assertDatabaseMissing('work_items', [
        'source_reference' => 'org/my-repo#1',
    ]);
});

it('shows the work item detail page', function () {
    $this->actingAs($this->user)
        ->get(route('work-items.show', $this->workItem))
        ->assertOk()
        ->assertSee($this->workItem->title);
});

it('can update a work item', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.edit', ['workItem' => $this->workItem])
        ->set('title', 'Updated Work Item')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($this->workItem->fresh()->title)->toBe('Updated Work Item');
});

it('can delete a work item', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->call('delete', $this->workItem->id);

    $this->assertDatabaseMissing('work_items', [
        'id' => $this->workItem->id,
    ]);
});

it('prevents access to work items from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherWorkItem = WorkItem::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('work-items.show', $otherWorkItem))
        ->assertForbidden();
});

it('requires authentication for index', function () {
    $this->get(route('work-items.index'))
        ->assertRedirect(route('login'));
});
