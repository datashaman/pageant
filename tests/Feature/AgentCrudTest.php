<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->agent = Agent::factory()->for($this->organization)->create();
});

it('shows the agents index page', function () {
    $this->actingAs($this->user)
        ->get(route('agents.index'))
        ->assertOk()
        ->assertSee($this->agent->name);
});

it('can create an agent', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('organization_id', $this->organization->id)
        ->set('name', 'test-agent')
        ->set('description', 'A test agent')
        ->set('provider', 'anthropic')
        ->set('model', 'claude-sonnet')
        ->set('permission_mode', 'full')
        ->set('max_turns', 10)
        ->set('background', false)
        ->set('isolation', 'false')
        ->set('toolsText', 'read, write, edit')
        ->set('disallowedToolsText', 'bash')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $agent = Agent::where('name', 'test-agent')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->tools)->toBe(['read', 'write', 'edit'])
        ->and($agent->disallowed_tools)->toBe(['bash']);
});

it('validates required fields on create', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('name', '')
        ->set('organization_id', '')
        ->call('save')
        ->assertHasErrors(['name', 'organization_id']);
});

it('shows the agent detail page', function () {
    $this->actingAs($this->user)
        ->get(route('agents.show', $this->agent))
        ->assertOk()
        ->assertSee($this->agent->name);
});

it('can update an agent', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.edit', ['agent' => $this->agent])
        ->set('name', 'updated-agent')
        ->set('toolsText', 'grep, glob')
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect();

    $fresh = $this->agent->fresh();
    expect($fresh->name)->toBe('updated-agent')
        ->and($fresh->tools)->toBe(['grep', 'glob']);
});

it('can delete an agent', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.index')
        ->call('delete', $this->agent->id);

    $this->assertDatabaseMissing('agents', [
        'id' => $this->agent->id,
    ]);
});

it('prevents access to agents from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherAgent = Agent::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('agents.show', $otherAgent))
        ->assertForbidden();
});

it('requires authentication for index', function () {
    $this->get(route('agents.index'))
        ->assertRedirect(route('login'));
});
