<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;

it('can be created via factory', function () {
    $repo = Repo::factory()->create();

    expect($repo)->toBeInstanceOf(Repo::class)
        ->and($repo->id)->not->toBeNull();
});

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $repo = Repo::factory()->for($organization)->create();

    expect($repo->organization->id)->toBe($organization->id);
});

it('has skills relationship', function () {
    $repo = Repo::factory()->create();
    $skill = Skill::factory()->create();
    $repo->skills()->attach($skill);

    expect($repo->skills)->toHaveCount(1);
});

it('has agents relationship', function () {
    $repo = Repo::factory()->create();
    $agent = Agent::factory()->create();
    $repo->agents()->attach($agent);

    expect($repo->agents)->toHaveCount(1);
});

it('has projects relationship', function () {
    $repo = Repo::factory()->create();
    $project = Project::factory()->create();
    $repo->projects()->attach($project);

    expect($repo->projects)->toHaveCount(1);
});

it('can be scoped by source', function () {
    Repo::factory()->create(['source' => 'github']);
    Repo::factory()->create(['source' => 'gitlab']);

    expect(Repo::bySource('github')->count())->toBe(1)
        ->and(Repo::bySource('gitlab')->count())->toBe(1);
});
