<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkItem;
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

// ── Projects ────────────────────────────────────────────────────────

it('shows empty state on projects index when no projects exist', function () {
    $this->actingAs($this->user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('No projects yet')
        ->assertSee('Projects group related work items')
        ->assertDontSee('Search projects...');
});

it('shows table on projects index when projects exist', function () {
    $project = Project::factory()->for($this->organization)->create();

    $this->actingAs($this->user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertDontSee('No projects yet')
        ->assertSee($project->name);
});

it('shows no-match message when projects search yields no results', function () {
    Project::factory()->for($this->organization)->create(['name' => 'My Project']);

    Livewire::actingAs($this->user)
        ->test('pages::projects.index')
        ->set('search', 'nonexistent-project-xyz')
        ->assertSee('No projects match your search.')
        ->assertDontSee('No projects yet');
});

// ── Agents ──────────────────────────────────────────────────────────

it('shows empty state on agents index when no agents exist', function () {
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

// ── Repos ───────────────────────────────────────────────────────────

it('shows empty state on repos index when no repos exist', function () {
    $this->actingAs($this->user)
        ->get(route('repos.index'))
        ->assertOk()
        ->assertSee('No repos yet')
        ->assertSee('Import repositories from your GitHub installations')
        ->assertDontSee('Search repos...');
});

it('shows table on repos index when repos exist', function () {
    $repo = Repo::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/test-repo',
    ]);

    $this->actingAs($this->user)
        ->get(route('repos.index'))
        ->assertOk()
        ->assertDontSee('No repos yet')
        ->assertSee($repo->name);
});

it('shows no-match message when repos search yields no results', function () {
    Repo::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/real-repo',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::repos.index')
        ->set('search', 'nonexistent-repo-xyz')
        ->assertSee('No repos match your search.')
        ->assertDontSee('No repos yet');
});

// ── Work Items ──────────────────────────────────────────────────────

it('shows empty state on work items index when no work items exist', function () {
    $this->actingAs($this->user)
        ->get(route('work-items.index'))
        ->assertOk()
        ->assertSee('No work items yet')
        ->assertSee('Work items track GitHub issues and tasks')
        ->assertDontSee('Search work items...');
});

it('shows table on work items index when work items exist', function () {
    $workItem = WorkItem::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/repo#1',
    ]);

    $this->actingAs($this->user)
        ->get(route('work-items.index'))
        ->assertOk()
        ->assertDontSee('No work items yet')
        ->assertSee($workItem->title);
});

it('shows no-match message when work items search yields no results', function () {
    WorkItem::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/repo#1',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->set('search', 'nonexistent-work-item-xyz')
        ->assertSee('No work items match your search.')
        ->assertDontSee('No work items yet');
});

it('shows search and table when work items statusFilter is changed on empty list', function () {
    Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->set('statusFilter', 'closed')
        ->assertSee('Search work items...')
        ->assertDontSee('No work items yet');
});

it('shows search and table when work items statusFilter is all on empty list', function () {
    Livewire::actingAs($this->user)
        ->test('pages::work-items.index')
        ->set('statusFilter', 'all')
        ->assertSee('Search work items...')
        ->assertDontSee('No work items yet');
});
