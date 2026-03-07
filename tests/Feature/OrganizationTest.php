<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkItem;

it('can be created via factory', function () {
    $organization = Organization::factory()->create();

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->id)->not->toBeNull()
        ->and($organization->title)->not->toBeEmpty()
        ->and($organization->slug)->not->toBeEmpty();
});

it('enforces unique title', function () {
    Organization::factory()->create(['title' => 'Acme Corp']);

    Organization::factory()->create(['title' => 'Acme Corp']);
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

it('has repos relationship', function () {
    $organization = Organization::factory()->create();
    Repo::factory()->for($organization)->create();

    expect($organization->repos)->toHaveCount(1);
});

it('has skills relationship', function () {
    $organization = Organization::factory()->create();
    Skill::factory()->for($organization)->create();

    expect($organization->skills)->toHaveCount(1);
});

it('has agents relationship', function () {
    $organization = Organization::factory()->create();
    Agent::factory()->for($organization)->create();

    expect($organization->agents)->toHaveCount(1);
});

it('has projects relationship', function () {
    $organization = Organization::factory()->create();
    Project::factory()->for($organization)->create();

    expect($organization->projects)->toHaveCount(1);
});

it('has work items relationship', function () {
    $organization = Organization::factory()->create();
    WorkItem::factory()->for($organization)->create();

    expect($organization->workItems)->toHaveCount(1);
});
