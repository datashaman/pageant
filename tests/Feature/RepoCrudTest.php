<?php

use App\Models\Organization;
use App\Models\Repo;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->repo = Repo::factory()->for($this->organization)->create();
});

it('shows the repos index page', function () {
    $this->actingAs($this->user)
        ->get(route('repos.index'))
        ->assertOk()
        ->assertSee($this->repo->name);
});

it('can create a repo', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.create')
        ->set('organization_id', $this->organization->id)
        ->set('name', 'new-repo')
        ->set('source', 'github')
        ->set('source_reference', 'main')
        ->set('source_url', 'https://github.com/example/new-repo')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('repos', [
        'name' => 'new-repo',
        'source' => 'github',
    ]);
});

it('validates required fields on create', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.create')
        ->set('name', '')
        ->set('organization_id', '')
        ->call('save')
        ->assertHasErrors(['name', 'organization_id']);
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
