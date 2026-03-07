<?php

use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
});

it('shows the organizations index page', function () {
    $this->actingAs($this->user)
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertSee($this->organization->title);
});

it('shows the create organization page', function () {
    $this->actingAs($this->user)
        ->get(route('organizations.create'))
        ->assertOk();
});

it('can create an organization', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::organizations.create')
        ->set('title', 'New Test Org')
        ->set('slug', 'new-test-org')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('organizations', [
        'title' => 'New Test Org',
        'slug' => 'new-test-org',
    ]);
});

it('validates required fields on create', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::organizations.create')
        ->set('title', '')
        ->set('slug', '')
        ->call('save')
        ->assertHasErrors(['title', 'slug']);
});

it('shows the organization detail page', function () {
    $this->actingAs($this->user)
        ->get(route('organizations.show', $this->organization))
        ->assertOk()
        ->assertSee($this->organization->title);
});

it('shows the edit organization page', function () {
    $this->actingAs($this->user)
        ->get(route('organizations.edit', $this->organization))
        ->assertOk();
});

it('can update an organization', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::organizations.edit', ['organization' => $this->organization])
        ->set('title', 'Updated Title')
        ->set('slug', 'updated-title')
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($this->organization->fresh()->title)->toBe('Updated Title');
});

it('can delete an organization from index', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::organizations.index')
        ->call('delete', $this->organization->id);

    $this->assertDatabaseMissing('organizations', [
        'id' => $this->organization->id,
    ]);
});

it('prevents access to organizations from other users', function () {
    $otherUser = User::factory()->create();
    $otherOrg = Organization::factory()->create();
    $otherUser->organizations()->attach($otherOrg);

    $this->actingAs($this->user)
        ->get(route('organizations.show', $otherOrg))
        ->assertForbidden();
});

it('requires authentication for index', function () {
    $this->get(route('organizations.index'))
        ->assertRedirect(route('login'));
});
