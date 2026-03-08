<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\GitHubService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

it('shows empty state on skills index when no skills exist', function () {
    $this->actingAs($this->user)
        ->get(route('skills.index'))
        ->assertOk()
        ->assertSee('No skills yet')
        ->assertSee('Create Skill');
});

it('shows table on skills index when skills exist', function () {
    $skill = Skill::factory()->for($this->organization)->create();

    $this->actingAs($this->user)
        ->get(route('skills.index'))
        ->assertOk()
        ->assertDontSee('No skills yet')
        ->assertSee($skill->name);
});

it('shows empty state on projects index when no projects exist', function () {
    $this->actingAs($this->user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('No projects yet')
        ->assertSee('Create Project');
});

it('shows table on projects index when projects exist', function () {
    $project = Project::factory()->for($this->organization)->create();

    $this->actingAs($this->user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertDontSee('No projects yet')
        ->assertSee($project->name);
});

it('shows empty state on agents index when no agents exist', function () {
    $this->actingAs($this->user)
        ->get(route('agents.index'))
        ->assertOk()
        ->assertSee('No agents yet')
        ->assertSee('Create Agent');
});

it('shows table on agents index when agents exist', function () {
    $agent = Agent::factory()->for($this->organization)->create();

    $this->actingAs($this->user)
        ->get(route('agents.index'))
        ->assertOk()
        ->assertDontSee('No agents yet')
        ->assertSee($agent->name);
});

it('shows empty state on repos index when no repos exist', function () {
    $mock = Mockery::mock(GitHubService::class);
    $mock->shouldReceive('listRepositories')->andReturn([]);
    app()->instance(GitHubService::class, $mock);

    $this->actingAs($this->user)
        ->get(route('repos.index'))
        ->assertOk()
        ->assertSee('No repos yet')
        ->assertSee('Add Repos');
});

it('shows table on repos index when repos exist', function () {
    $mock = Mockery::mock(GitHubService::class);
    $mock->shouldReceive('listRepositories')->andReturn([]);
    app()->instance(GitHubService::class, $mock);

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

it('shows empty state on work items index when no work items exist', function () {
    $this->actingAs($this->user)
        ->get(route('work-items.index'))
        ->assertOk()
        ->assertSee('No work items yet')
        ->assertSee('Import Issues');
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
