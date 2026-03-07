<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\WorkItem;

it('can be created via factory', function () {
    $project = Project::factory()->create();

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->id)->not->toBeNull();
});

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();

    expect($project->organization->id)->toBe($organization->id);
});

it('has repos relationship', function () {
    $project = Project::factory()->create();
    $repo = Repo::factory()->create();
    $project->repos()->attach($repo);

    expect($project->repos)->toHaveCount(1);
});

it('has work items relationship', function () {
    $project = Project::factory()->create();
    WorkItem::factory()->for($project)->create();

    expect($project->workItems)->toHaveCount(1);
});
