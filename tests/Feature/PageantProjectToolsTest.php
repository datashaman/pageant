<?php

use App\Ai\Tools\AttachRepoToProjectTool;
use App\Ai\Tools\CreateProjectTool;
use App\Ai\Tools\DeleteProjectTool;
use App\Ai\Tools\DetachRepoFromProjectTool;
use App\Ai\Tools\GetProjectTool;
use App\Ai\Tools\UpdateProjectTool;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create([
        'current_organization_id' => $this->organization->id,
    ]);
    $this->user->organizations()->attach($this->organization);
});

it('creates a project', function () {
    $tool = new CreateProjectTool($this->user);
    $result = json_decode($tool->handle(new Request([
        'name' => 'My Project',
        'description' => 'A description',
    ])), true);

    expect($result['name'])->toBe('My Project')
        ->and($result['description'])->toBe('A description')
        ->and($result['organization_id'])->toBe($this->organization->id);
});

it('gets a project by ID with repos', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Test Project',
    ]);
    $repo = Repo::factory()->create(['organization_id' => $this->organization->id]);
    $project->repos()->attach($repo);

    $tool = new GetProjectTool($this->user);
    $result = json_decode($tool->handle(new Request(['id' => $project->id])), true);

    expect($result['name'])->toBe('Test Project')
        ->and($result['repos'])->toHaveCount(1);
});

it('updates a project', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Old Name',
        'description' => 'Old desc',
    ]);

    $tool = new UpdateProjectTool($this->user);
    $result = json_decode($tool->handle(new Request([
        'id' => $project->id,
        'name' => 'New Name',
    ])), true);

    expect($result['name'])->toBe('New Name')
        ->and($result['description'])->toBe('Old desc');
});

it('deletes a project', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Doomed Project',
    ]);

    $tool = new DeleteProjectTool($this->user);
    $result = $tool->handle(new Request(['id' => $project->id]));

    expect($result)->toContain('Doomed Project')
        ->toContain('deleted')
        ->and(Project::find($project->id))->toBeNull();
});

it('attaches a repo to a project', function () {
    $project = Project::factory()->create(['organization_id' => $this->organization->id]);
    $repo = Repo::factory()->create(['organization_id' => $this->organization->id]);

    $tool = new AttachRepoToProjectTool($this->user);
    $result = json_decode($tool->handle(new Request([
        'project_id' => $project->id,
        'repo_id' => $repo->id,
    ])), true);

    expect($result['repos'])->toHaveCount(1)
        ->and($result['repos'][0]['id'])->toBe($repo->id);
});

it('attaching is idempotent', function () {
    $project = Project::factory()->create(['organization_id' => $this->organization->id]);
    $repo = Repo::factory()->create(['organization_id' => $this->organization->id]);

    $tool = new AttachRepoToProjectTool($this->user);
    $tool->handle(new Request(['project_id' => $project->id, 'repo_id' => $repo->id]));
    $result = json_decode($tool->handle(new Request([
        'project_id' => $project->id,
        'repo_id' => $repo->id,
    ])), true);

    expect($result['repos'])->toHaveCount(1);
});

it('detaches a repo from a project', function () {
    $project = Project::factory()->create(['organization_id' => $this->organization->id]);
    $repo = Repo::factory()->create(['organization_id' => $this->organization->id]);
    $project->repos()->attach($repo);

    $tool = new DetachRepoFromProjectTool($this->user);
    $result = $tool->handle(new Request([
        'project_id' => $project->id,
        'repo_id' => $repo->id,
    ]));

    expect($result)->toContain('detached')
        ->and($project->fresh()->repos)->toHaveCount(0);
});
