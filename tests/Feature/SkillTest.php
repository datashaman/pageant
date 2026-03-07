<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\Skill;

it('can be created via factory', function () {
    $skill = Skill::factory()->create();

    expect($skill)->toBeInstanceOf(Skill::class)
        ->and($skill->id)->not->toBeNull();
});

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $skill = Skill::factory()->for($organization)->create();

    expect($skill->organization->id)->toBe($organization->id);
});

it('casts allowed_tools as array', function () {
    $skill = Skill::factory()->create(['allowed_tools' => ['read', 'write']]);
    $skill->refresh();

    expect($skill->allowed_tools)->toBe(['read', 'write']);
});

it('casts enabled as boolean', function () {
    $skill = Skill::factory()->create(['enabled' => false]);
    $skill->refresh();

    expect($skill->enabled)->toBeFalse();
});

it('belongs to an agent', function () {
    $agent = Agent::factory()->create();
    $skill = Skill::factory()->create(['agent_id' => $agent->id]);

    expect($skill->agent->id)->toBe($agent->id);
});

it('can have null agent', function () {
    $skill = Skill::factory()->create(['agent_id' => null]);

    expect($skill->agent)->toBeNull();
});

it('has agents relationship', function () {
    $skill = Skill::factory()->create();
    $agent = Agent::factory()->create();
    $skill->agents()->attach($agent);

    expect($skill->agents)->toHaveCount(1);
});

it('has repos relationship', function () {
    $skill = Skill::factory()->create();
    $repo = Repo::factory()->create();
    $skill->repos()->attach($repo);

    expect($skill->repos)->toHaveCount(1);
});

it('enforces unique name per organization', function () {
    $organization = Organization::factory()->create();

    Skill::factory()->for($organization)->create(['name' => 'deploy']);
    Skill::factory()->for($organization)->create(['name' => 'deploy']);
})->throws(\Illuminate\Database\QueryException::class);

it('can be scoped by source', function () {
    Skill::factory()->create(['source' => 'github']);
    Skill::factory()->create(['source' => 'gitlab']);

    expect(Skill::bySource('github')->count())->toBe(1)
        ->and(Skill::bySource('gitlab')->count())->toBe(1);
});
