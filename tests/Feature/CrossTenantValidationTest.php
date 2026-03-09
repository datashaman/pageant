<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);

    $this->otherOrg = Organization::factory()->create();
});

it('rejects cross-org project_id when editing work items', function () {
    $workItem = WorkItem::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#1',
    ]);
    $otherProject = Project::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.edit', ['workItem' => $workItem])
        ->set('title', 'Updated title')
        ->set('project_id', $otherProject->id)
        ->call('save')
        ->assertHasErrors(['project_id']);
});

it('accepts same-org project_id when editing work items', function () {
    $workItem = WorkItem::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#1',
    ]);
    $sameProject = Project::factory()->for($this->organization)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.edit', ['workItem' => $workItem])
        ->set('title', 'Updated title')
        ->set('project_id', $sameProject->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($workItem->fresh()->project_id)->toBe($sameProject->id);
});

it('accepts same-org skills and repos when editing agents', function () {
    $agent = Agent::factory()->for($this->organization)->create();
    $sameSkill = Skill::factory()->for($this->organization)->create();
    $sameRepo = Repo::factory()->for($this->organization)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.edit', ['agent' => $agent])
        ->set('name', $agent->name)
        ->set('selectedSkills', [$sameSkill->id])
        ->set('selectedRepos', [$sameRepo->id])
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($agent->fresh()->skills->pluck('id')->toArray())->toBe([$sameSkill->id])
        ->and($agent->fresh()->repos->pluck('id')->toArray())->toBe([$sameRepo->id]);
});

it('accepts same-org agents and repos when editing skills', function () {
    $skill = Skill::factory()->for($this->organization)->create();
    $sameAgent = Agent::factory()->for($this->organization)->create();
    $sameRepo = Repo::factory()->for($this->organization)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.edit', ['skill' => $skill])
        ->set('name', $skill->name)
        ->set('selectedAgents', [$sameAgent->id])
        ->set('selectedRepos', [$sameRepo->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($skill->fresh()->agents->pluck('id')->toArray())->toBe([$sameAgent->id])
        ->and($skill->fresh()->repos->pluck('id')->toArray())->toBe([$sameRepo->id]);
});

it('accepts same-org skills, agents, and projects when editing repos', function () {
    $repo = Repo::factory()->for($this->organization)->create();
    $sameSkill = Skill::factory()->for($this->organization)->create();
    $sameAgent = Agent::factory()->for($this->organization)->create();
    $sameProject = Project::factory()->for($this->organization)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $repo])
        ->set('name', $repo->name)
        ->set('selectedSkills', [$sameSkill->id])
        ->set('selectedAgents', [$sameAgent->id])
        ->set('selectedProjects', [$sameProject->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($repo->fresh()->skills->pluck('id')->toArray())->toBe([$sameSkill->id])
        ->and($repo->fresh()->agents->pluck('id')->toArray())->toBe([$sameAgent->id])
        ->and($repo->fresh()->projects->pluck('id')->toArray())->toBe([$sameProject->id]);
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

it('rejects cross-org repos when editing agents', function () {
    $agent = Agent::factory()->for($this->organization)->create();
    $otherRepo = Repo::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.edit', ['agent' => $agent])
        ->set('name', $agent->name)
        ->set('selectedRepos', [$otherRepo->id])
        ->call('update')
        ->assertHasErrors(['selectedRepos.0']);
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

it('rejects cross-org repos when creating agents', function () {
    $otherRepo = Repo::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::agents.create')
        ->set('name', 'test-agent')
        ->set('selectedRepos', [$otherRepo->id])
        ->call('save')
        ->assertHasErrors(['selectedRepos.0']);
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

it('rejects cross-org repos when editing skills', function () {
    $skill = Skill::factory()->for($this->organization)->create();
    $otherRepo = Repo::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.edit', ['skill' => $skill])
        ->set('name', $skill->name)
        ->set('selectedRepos', [$otherRepo->id])
        ->call('save')
        ->assertHasErrors(['selectedRepos.0']);
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

it('rejects cross-org repos when creating skills', function () {
    $otherRepo = Repo::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.create')
        ->set('name', 'test-skill')
        ->set('selectedRepos', [$otherRepo->id])
        ->call('save')
        ->assertHasErrors(['selectedRepos.0']);
});

it('rejects cross-org skills when editing repos', function () {
    $repo = Repo::factory()->for($this->organization)->create();
    $otherSkill = Skill::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $repo])
        ->set('name', $repo->name)
        ->set('selectedSkills', [$otherSkill->id])
        ->call('save')
        ->assertHasErrors(['selectedSkills.0']);
});

it('rejects cross-org agents when editing repos', function () {
    $repo = Repo::factory()->for($this->organization)->create();
    $otherAgent = Agent::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $repo])
        ->set('name', $repo->name)
        ->set('selectedAgents', [$otherAgent->id])
        ->call('save')
        ->assertHasErrors(['selectedAgents.0']);
});

it('rejects cross-org projects when editing repos', function () {
    $repo = Repo::factory()->for($this->organization)->create();
    $otherProject = Project::factory()->for($this->otherOrg)->create();

    Livewire\Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $repo])
        ->set('name', $repo->name)
        ->set('selectedProjects', [$otherProject->id])
        ->call('save')
        ->assertHasErrors(['selectedProjects.0']);
});
