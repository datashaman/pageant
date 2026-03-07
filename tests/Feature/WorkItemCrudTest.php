<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->project = Project::factory()->for($this->organization)->create();
    $this->workItem = WorkItem::factory()->for($this->organization)->forProject($this->project)->create();
});

it('shows the work items index page', function () {
    $this->actingAs($this->user)
        ->get(route('work-items.index'))
        ->assertOk()
        ->assertSee($this->workItem->title);
});

it('can create a work item', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.create')
        ->set('organization_id', $this->organization->id)
        ->set('title', 'New Work Item')
        ->set('description', 'A test work item')
        ->set('source', 'github')
        ->set('project_id', $this->project->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('work_items', [
        'title' => 'New Work Item',
        'organization_id' => $this->organization->id,
    ]);
});

it('validates required fields on create', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.create')
        ->set('organization_id', $this->organization->id)
        ->set('title', '')
        ->set('source', '')
        ->call('save')
        ->assertHasErrors(['title', 'source']);
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
