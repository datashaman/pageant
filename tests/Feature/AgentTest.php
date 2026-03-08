<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\Skill;

it('can be created via factory', function () {
    $agent = Agent::factory()->create();

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->id)->not->toBeNull();
});

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $agent = Agent::factory()->for($organization)->create();

    expect($agent->organization->id)->toBe($organization->id);
});

it('casts tools as array', function () {
    $agent = Agent::factory()->create(['tools' => ['tool1', 'tool2']]);
    $agent->refresh();

    expect($agent->tools)->toBe(['tool1', 'tool2']);
});

it('casts background as boolean', function () {
    $agent = Agent::factory()->create(['background' => true]);
    $agent->refresh();

    expect($agent->background)->toBeTrue();
});

it('has skills relationship', function () {
    $agent = Agent::factory()->create();
    $skill = Skill::factory()->create();
    $agent->skills()->attach($skill);

    expect($agent->skills)->toHaveCount(1);
});

it('has repos relationship', function () {
    $agent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $agent->repos()->attach($repo);

    expect($agent->repos)->toHaveCount(1);
});

it('enforces unique name per organization', function () {
    $organization = Organization::factory()->create();

    Agent::factory()->for($organization)->create(['name' => 'builder']);
    Agent::factory()->for($organization)->create(['name' => 'builder']);
})->throws(\Illuminate\Database\QueryException::class);

it('allows same name in different organizations', function () {
    $agent1 = Agent::factory()->create(['name' => 'builder']);
    $agent2 = Agent::factory()->create(['name' => 'builder']);

    expect($agent1->id)->not->toBe($agent2->id);
});

it('returns Default for model_display_name when model is inherit', function () {
    $agent = Agent::factory()->create(['model' => 'inherit']);

    expect($agent->model_display_name)->toBe('Default');
});

it('returns the model name for model_display_name when model is not inherit', function () {
    $agent = Agent::factory()->create(['model' => 'claude-sonnet-4-6']);

    expect($agent->model_display_name)->toBe('claude-sonnet-4-6');
});
