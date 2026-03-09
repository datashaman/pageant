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
    $mock->shouldReceive('getIssue')->andReturn(['number' => 1, 'state' => 'open']);
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

it('hides empty optional fields on work item detail page', function () {
    $workItem = WorkItem::factory()->for($this->organization)->create([
        'description' => '',
        'board_id' => '',
        'source' => 'github',
        'source_reference' => '',
        'source_url' => null,
        'project_id' => null,
    ]);

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $workItem])
        ->assertDontSee('Board ID')
        ->assertDontSee('Source Reference')
        ->assertDontSee('Source URL')
        ->assertDontSee('Project')
        ->assertDontSee('Description');
});

it('shows populated optional fields on work item detail page', function () {
    $workItem = WorkItem::factory()->for($this->organization)->forProject($this->project)->create([
        'description' => 'A detailed description',
        'board_id' => 'BOARD-123',
        'source' => 'github',
        'source_reference' => 'org/repo#5',
        'source_url' => 'https://github.com/org/repo/issues/5',
    ]);

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $workItem])
        ->assertSee('Board ID')
        ->assertSee('BOARD-123')
        ->assertSee('Source Reference')
        ->assertSee('org/repo#5')
        ->assertSee('Source URL')
        ->assertSee('Project')
        ->assertSee($this->project->name)
        ->assertSee('Description')
        ->assertSee('A detailed description');
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

it('can close a work item from the index', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->call('close', $this->workItem->id)
        ->assertDispatched('close-modal', id: 'confirm-close-'.$this->workItem->id);

    expect($this->workItem->fresh()->status)->toBe('closed');
});

it('can reopen a closed work item from the index', function () {
    $this->workItem->update(['status' => 'closed']);

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->call('reopen', $this->workItem->id);

    expect($this->workItem->fresh()->status)->toBe('open');
});

it('can close a work item from the show page', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem])
        ->call('close')
        ->assertDispatched('close-modal', id: 'confirm-close');

    expect($this->workItem->fresh()->status)->toBe('closed');
});

it('can reopen a closed work item from the show page', function () {
    $this->workItem->update(['status' => 'closed']);

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem])
        ->call('reopen');

    expect($this->workItem->fresh()->status)->toBe('open');
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
