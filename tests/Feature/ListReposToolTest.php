<?php

use App\Ai\Tools\ListReposTool;
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
});

it('lists repos for the current organization', function () {
    Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'my-repo',
        'source' => 'github',
        'source_reference' => 'acme/my-repo',
    ]);

    $tool = new ListReposTool($this->user);
    $result = json_decode($tool->handle(new Request([])), true);

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('my-repo')
        ->and($result[0]['source_reference'])->toBe('acme/my-repo');
});

it('does not list repos from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    Repo::factory()->create(['organization_id' => $otherOrg->id]);

    $tool = new ListReposTool($this->user);
    $result = json_decode($tool->handle(new Request([])), true);

    expect($result)->toBeEmpty();
});

it('returns an empty array when no repos exist', function () {
    $tool = new ListReposTool($this->user);
    $result = json_decode($tool->handle(new Request([])), true);

    expect($result)->toBeEmpty();
});
