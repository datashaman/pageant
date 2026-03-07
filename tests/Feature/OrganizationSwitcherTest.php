<?php

use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org1 = Organization::factory()->create(['name' => 'Org One']);
    $this->org2 = Organization::factory()->create(['name' => 'Org Two']);
    $this->user->organizations()->attach([$this->org1->id, $this->org2->id]);
});

it('renders switcher when user has multiple organizations', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('organization-switcher')
        ->assertSee('Org One')
        ->assertSee('Org Two');
});

it('does not render switcher when user has one organization', function () {
    $this->user->organizations()->detach($this->org2->id);

    Livewire\Livewire::actingAs($this->user)
        ->test('organization-switcher')
        ->assertDontSee('Org One');
});

it('updates current organization on change', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('organization-switcher')
        ->set('currentOrganizationId', $this->org2->id)
        ->assertRedirect();

    expect($this->user->fresh()->current_organization_id)->toBe($this->org2->id);
});

it('returns null when no current org is set', function () {
    expect($this->user->currentOrganizationId())->toBeNull();
});

it('returns current organization when set', function () {
    $this->user->update(['current_organization_id' => $this->org2->id]);

    expect($this->user->currentOrganizationId())->toBe($this->org2->id);
});
