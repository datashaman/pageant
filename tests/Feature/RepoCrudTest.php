<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\User;
use App\Services\GitHubService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->installation = GithubInstallation::factory()->for($this->organization)->create();
    $this->repo = Repo::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/existing-repo',
    ]);

    $mock = Mockery::mock(GitHubService::class);
    $mock->shouldReceive('listRepositories')->andReturn([]);
    app()->instance(GitHubService::class, $mock);
});

it('shows the repos index page', function () {
    $this->actingAs($this->user)
        ->get(route('repos.index'))
        ->assertOk()
        ->assertSee($this->repo->name);
});

it('can track a github repo', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.index')
        ->set('showImportModal', true)
        ->set('selectedInstallationId', $this->installation->id)
        ->call('trackRepo', 'org/new-repo', 'https://github.com/org/new-repo');

    $this->assertDatabaseHas('repos', [
        'name' => 'new-repo',
        'source' => 'github',
        'source_reference' => 'org/new-repo',
        'source_url' => 'https://github.com/org/new-repo',
        'organization_id' => $this->organization->id,
    ]);
});

it('does not duplicate an already tracked repo', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.index')
        ->set('showImportModal', true)
        ->set('selectedInstallationId', $this->installation->id)
        ->call('trackRepo', 'org/existing-repo', 'https://github.com/org/existing-repo');

    expect(Repo::query()->where('source_reference', 'org/existing-repo')->count())->toBe(1);
});

it('can untrack a repo', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.index')
        ->set('showImportModal', true)
        ->set('selectedInstallationId', $this->installation->id)
        ->call('untrackRepo', 'org/existing-repo');

    $this->assertDatabaseMissing('repos', [
        'source_reference' => 'org/existing-repo',
    ]);
});

it('shows the repo detail page', function () {
    $this->actingAs($this->user)
        ->get(route('repos.show', $this->repo))
        ->assertOk()
        ->assertSee($this->repo->name);
});

it('can update a repo', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $this->repo])
        ->set('name', 'updated-repo')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($this->repo->fresh()->name)->toBe('updated-repo');
});

it('can delete a repo', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.index')
        ->call('delete', $this->repo->id);

    $this->assertDatabaseMissing('repos', [
        'id' => $this->repo->id,
    ]);
});

it('prevents access to repos from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherRepo = Repo::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('repos.show', $otherRepo))
        ->assertForbidden();
});

it('requires authentication for index', function () {
    $this->get(route('repos.index'))
        ->assertRedirect(route('login'));
});
