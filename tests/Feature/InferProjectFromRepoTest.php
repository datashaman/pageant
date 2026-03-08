<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('infers project ID when repo belongs to exactly one project', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->repo->projects()->attach($project);

    expect($this->repo->inferProjectId())->toBe($project->id);
});

it('returns null when repo belongs to no projects', function () {
    expect($this->repo->inferProjectId())->toBeNull();
});

it('returns null when repo belongs to multiple projects', function () {
    $projectA = Project::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $projectB = Project::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->repo->projects()->attach([$projectA->id, $projectB->id]);

    expect($this->repo->inferProjectId())->toBeNull();
});
