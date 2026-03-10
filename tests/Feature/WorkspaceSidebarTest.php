<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

it('renders repos for the current organization', function () {
    $repo = Repo::factory()->for($this->organization)->create(['name' => 'my-test-repo']);

    Livewire\Livewire::actingAs($this->user)
        ->test('workspace-sidebar')
        ->assertSee('my-test-repo');
});

it('shows active work items nested under their repo', function () {
    Repo::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/my-repo',
    ]);

    $project = Project::factory()->for($this->organization)->create();

    WorkItem::factory()
        ->for($this->organization)
        ->forProject($project)
        ->create([
            'title' => 'Fix the sidebar bug',
            'source' => 'github',
            'source_reference' => 'org/my-repo#42',
            'status' => 'open',
        ]);

    Livewire\Livewire::actingAs($this->user)
        ->test('workspace-sidebar')
        ->assertSee('Fix the sidebar bug');
});

it('does not show closed work items', function () {
    Repo::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/my-repo',
    ]);

    $project = Project::factory()->for($this->organization)->create();

    WorkItem::factory()
        ->for($this->organization)
        ->forProject($project)
        ->create([
            'title' => 'Closed item',
            'source' => 'github',
            'source_reference' => 'org/my-repo#10',
            'status' => 'closed',
        ]);

    Livewire\Livewire::actingAs($this->user)
        ->test('workspace-sidebar')
        ->assertDontSee('Closed item');
});

it('shows empty state when no repos exist', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('workspace-sidebar')
        ->assertSee('No repos yet.');
});

it('shows the add repository button', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('workspace-sidebar')
        ->assertSee('Add Repository');
});
