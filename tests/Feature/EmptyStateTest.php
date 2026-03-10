<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

// ── Skills ──────────────────────────────────────────────────────────

it('shows empty state on skills index when no skills exist', function () {
    $this->actingAs($this->user)
        ->get(route('skills.index'))
        ->assertOk()
        ->assertSee('No skills yet')
        ->assertSee('Skills define reusable capabilities')
        ->assertDontSee('Search skills...');
});

it('shows table on skills index when skills exist', function () {
    $skill = Skill::factory()->for($this->organization)->create();

    $this->actingAs($this->user)
        ->get(route('skills.index'))
        ->assertOk()
        ->assertDontSee('No skills yet')
        ->assertSee($skill->name);
});

it('shows no-match message when skills search yields no results', function () {
    Skill::factory()->for($this->organization)->create(['name' => 'Code Review']);

    Livewire::actingAs($this->user)
        ->test('pages::skills.index')
        ->set('search', 'nonexistent-skill-xyz')
        ->assertSee('No skills match your search.')
        ->assertDontSee('No skills yet');
});

// ── Agents ──────────────────────────────────────────────────────────

it('shows empty state on agents index when no agents exist', function () {
    // Remove the auto-created planning agent so the empty state shows
    $this->organization->agents()->delete();
    $this->organization->update(['planning_agent_id' => null]);

    $this->actingAs($this->user)
        ->get(route('agents.index'))
        ->assertOk()
        ->assertSee('No agents yet')
        ->assertSee('Agents are AI-powered workers')
        ->assertDontSee('Search agents...');
});

it('shows table on agents index when agents exist', function () {
    $agent = Agent::factory()->for($this->organization)->create();

    $this->actingAs($this->user)
        ->get(route('agents.index'))
        ->assertOk()
        ->assertDontSee('No agents yet')
        ->assertSee($agent->name);
});

it('shows no-match message when agents search yields no results', function () {
    Agent::factory()->for($this->organization)->create(['name' => 'Review Bot']);

    Livewire::actingAs($this->user)
        ->test('pages::agents.index')
        ->set('search', 'nonexistent-agent-xyz')
        ->assertSee('No agents match your search.')
        ->assertDontSee('No agents yet');
});

// ── Workspaces ──────────────────────────────────────────────────────

it('shows empty state on workspaces index when no workspaces exist', function () {
    $this->actingAs($this->user)
        ->get(route('workspaces.index'))
        ->assertOk()
        ->assertSee('No workspaces yet')
        ->assertSee('Workspaces group repos and issues')
        ->assertDontSee('Search workspaces...');
});

it('shows table on workspaces index when workspaces exist', function () {
    $workspace = Workspace::factory()->for($this->organization)->create();

    $this->actingAs($this->user)
        ->get(route('workspaces.index'))
        ->assertOk()
        ->assertDontSee('No workspaces yet')
        ->assertSee($workspace->name);
});

it('shows no-match message when workspaces search yields no results', function () {
    Workspace::factory()->for($this->organization)->create(['name' => 'My Workspace']);

    Livewire::actingAs($this->user)
        ->test('pages::workspaces.index')
        ->set('search', 'nonexistent-workspace-xyz')
        ->assertSee('No workspaces match your search.')
        ->assertDontSee('No workspaces yet');
});
