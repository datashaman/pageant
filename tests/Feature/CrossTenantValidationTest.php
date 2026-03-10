<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);

    $this->otherOrg = Organization::factory()->create();
});

it('accepts same-org skills and workspaces when editing agents', function () {
    $agent = Agent::factory()->for($this->organization)->create();
    $sameSkill = Skill::factory()->for($this->organization)->create();
    $sameWorkspace = Workspace::factory()->for($this->organization)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.edit', ['agent' => $agent])
        ->set('name', $agent->name)
        ->set('selectedSkills', [$sameSkill->id])
        ->set('selectedWorkspaces', [$sameWorkspace->id])
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($agent->fresh()->skills->pluck('id')->toArray())->toBe([$sameSkill->id])
        ->and($agent->fresh()->workspaces->pluck('id')->toArray())->toBe([$sameWorkspace->id]);
});

it('accepts same-org agents and workspaces when editing skills', function () {
    $skill = Skill::factory()->for($this->organization)->create();
    $sameAgent = Agent::factory()->for($this->organization)->create();
    $sameWorkspace = Workspace::factory()->for($this->organization)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.edit', ['skill' => $skill])
        ->set('name', $skill->name)
        ->set('selectedAgents', [$sameAgent->id])
        ->set('selectedWorkspaces', [$sameWorkspace->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($skill->fresh()->agents->pluck('id')->toArray())->toBe([$sameAgent->id])
        ->and($skill->fresh()->workspaces->pluck('id')->toArray())->toBe([$sameWorkspace->id]);
});

it('rejects cross-org skills when editing agents', function () {
    $agent = Agent::factory()->for($this->organization)->create();
    $otherSkill = Skill::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.edit', ['agent' => $agent])
        ->set('name', $agent->name)
        ->set('selectedSkills', [$otherSkill->id])
        ->call('update')
        ->assertHasErrors(['selectedSkills.0']);
});

it('rejects cross-org workspaces when editing agents', function () {
    $agent = Agent::factory()->for($this->organization)->create();
    $otherWorkspace = Workspace::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.edit', ['agent' => $agent])
        ->set('name', $agent->name)
        ->set('selectedWorkspaces', [$otherWorkspace->id])
        ->call('update')
        ->assertHasErrors(['selectedWorkspaces.0']);
});

it('rejects cross-org skills when creating agents', function () {
    $otherSkill = Skill::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('name', 'test-agent')
        ->set('selectedSkills', [$otherSkill->id])
        ->call('save')
        ->assertHasErrors(['selectedSkills.0']);
});

it('rejects cross-org workspaces when creating agents', function () {
    $otherWorkspace = Workspace::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('name', 'test-agent')
        ->set('selectedWorkspaces', [$otherWorkspace->id])
        ->call('save')
        ->assertHasErrors(['selectedWorkspaces.0']);
});

it('rejects cross-org agents when editing skills', function () {
    $skill = Skill::factory()->for($this->organization)->create();
    $otherAgent = Agent::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.edit', ['skill' => $skill])
        ->set('name', $skill->name)
        ->set('selectedAgents', [$otherAgent->id])
        ->call('save')
        ->assertHasErrors(['selectedAgents.0']);
});

it('rejects cross-org workspaces when editing skills', function () {
    $skill = Skill::factory()->for($this->organization)->create();
    $otherWorkspace = Workspace::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.edit', ['skill' => $skill])
        ->set('name', $skill->name)
        ->set('selectedWorkspaces', [$otherWorkspace->id])
        ->call('save')
        ->assertHasErrors(['selectedWorkspaces.0']);
});

it('rejects cross-org agent_id when editing skills', function () {
    $skill = Skill::factory()->for($this->organization)->create();
    $otherAgent = Agent::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.edit', ['skill' => $skill])
        ->set('name', $skill->name)
        ->set('agent_id', $otherAgent->id)
        ->call('save')
        ->assertHasErrors(['agent_id']);
});

it('rejects cross-org agents when creating skills', function () {
    $otherAgent = Agent::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.create')
        ->set('name', 'test-skill')
        ->set('selectedAgents', [$otherAgent->id])
        ->call('save')
        ->assertHasErrors(['selectedAgents.0']);
});

it('rejects cross-org workspaces when creating skills', function () {
    $otherWorkspace = Workspace::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.create')
        ->set('name', 'test-skill')
        ->set('selectedWorkspaces', [$otherWorkspace->id])
        ->call('save')
        ->assertHasErrors(['selectedWorkspaces.0']);
});
