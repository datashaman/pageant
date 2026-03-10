<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Models\Workspace;

it('can be created via factory', function () {
    $organization = Organization::factory()->create();

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->id)->not->toBeNull()
        ->and($organization->name)->not->toBeEmpty()
        ->and($organization->slug)->not->toBeEmpty();
});

it('enforces unique title', function () {
    Organization::factory()->create(['name' => 'Acme Corp']);

    Organization::factory()->create(['name' => 'Acme Corp']);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces unique slug', function () {
    Organization::factory()->create(['slug' => 'acme-corp']);

    Organization::factory()->create(['slug' => 'acme-corp']);
})->throws(\Illuminate\Database\QueryException::class);

it('has users relationship', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);

    expect($organization->users)->toHaveCount(1)
        ->and($organization->users->first()->id)->toBe($user->id);
});

it('has workspaces relationship', function () {
    $organization = Organization::factory()->create();
    Workspace::factory()->for($organization)->create();

    expect($organization->workspaces)->toHaveCount(1);
});

it('has skills relationship', function () {
    $organization = Organization::factory()->create();
    Skill::factory()->for($organization)->create();

    expect($organization->skills)->toHaveCount(1);
});

it('has agents relationship', function () {
    $organization = Organization::factory()->create();
    Agent::factory()->for($organization)->create();

    // +1 for the auto-created planning agent from the observer
    expect($organization->agents)->toHaveCount(2);
});
