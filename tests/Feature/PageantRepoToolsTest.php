<?php

use App\Ai\Tools\DeleteRepoTool;
use App\Ai\Tools\GetRepoTool;
use App\Ai\Tools\UpdateRepoTool;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create([
        'current_organization_id' => $this->organization->id,
    ]);
    $this->user->organizations()->attach($this->organization);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'test-repo',
        'source' => 'github',
        'source_reference' => 'acme/test-repo',
    ]);
});

it('gets a repo by ID', function () {
    $tool = new GetRepoTool($this->user);
    $result = json_decode($tool->handle(new Request(['id' => $this->repo->id])), true);

    expect($result['id'])->toBe($this->repo->id)
        ->and($result['name'])->toBe('test-repo');
});

it('does not get a repo from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherRepo = Repo::factory()->create(['organization_id' => $otherOrg->id]);

    $tool = new GetRepoTool($this->user);

    expect(fn () => $tool->handle(new Request(['id' => $otherRepo->id])))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('updates a repo name', function () {
    $tool = new UpdateRepoTool($this->user);
    $result = json_decode($tool->handle(new Request([
        'id' => $this->repo->id,
        'name' => 'renamed-repo',
    ])), true);

    expect($result['name'])->toBe('renamed-repo')
        ->and($this->repo->fresh()->name)->toBe('renamed-repo');
});

it('deletes a repo', function () {
    $tool = new DeleteRepoTool($this->user);
    $result = $tool->handle(new Request(['id' => $this->repo->id]));

    expect($result)->toContain('test-repo')
        ->toContain('deleted')
        ->and(Repo::find($this->repo->id))->toBeNull();
});
