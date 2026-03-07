<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\WorkItem;

it('can be created via factory', function () {
    $workItem = WorkItem::factory()->create();

    expect($workItem)->toBeInstanceOf(WorkItem::class)
        ->and($workItem->id)->not->toBeNull();
});

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $workItem = WorkItem::factory()->for($organization)->create();

    expect($workItem->organization->id)->toBe($organization->id);
});

it('belongs to a project', function () {
    $project = Project::factory()->create();
    $workItem = WorkItem::factory()->forProject($project)->create([
        'organization_id' => $project->organization_id,
    ]);

    expect($workItem->project->id)->toBe($project->id);
});

it('can have null project', function () {
    $workItem = WorkItem::factory()->create();

    expect($workItem->project)->toBeNull();
});

it('can be scoped by source', function () {
    WorkItem::factory()->create(['source' => 'github']);
    WorkItem::factory()->create(['source' => 'jira']);

    expect(WorkItem::bySource('github')->count())->toBe(1)
        ->and(WorkItem::bySource('jira')->count())->toBe(1);
});
