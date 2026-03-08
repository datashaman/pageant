<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
    $this->project = Project::factory()->for($this->organization)->create();
});

it('shows the projects index page', function () {
    $this->actingAs($this->user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee($this->project->name);
});

it('can create a project', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::projects.create')
        ->set('name', 'New Project')
        ->set('description', 'A test project')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'name' => 'New Project',
        'organization_id' => $this->organization->id,
    ]);
});

it('validates required fields on create', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::projects.create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('shows the project detail page', function () {
    $this->actingAs($this->user)
        ->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertSee($this->project->name);
});

it('can update a project', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::projects.edit', ['project' => $this->project])
        ->set('name', 'Updated Project')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($this->project->fresh()->name)->toBe('Updated Project');
});

it('shows project name as a clickable link on the index page', function () {
    $this->actingAs($this->user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSeeHtml('href="' . route('projects.show', $this->project) . '"');
});

it('can delete a project', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::projects.index')
        ->call('delete', $this->project->id);

    $this->assertDatabaseMissing('projects', [
        'id' => $this->project->id,
    ]);
});

it('prevents access to projects from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherProject = Project::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('projects.show', $otherProject))
        ->assertForbidden();
});

it('requires authentication for index', function () {
    $this->get(route('projects.index'))
        ->assertRedirect(route('login'));
});
