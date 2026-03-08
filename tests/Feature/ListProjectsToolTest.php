<?php

use App\Ai\Tools\ListProjectsTool;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create([
        'current_organization_id' => $this->organization->id,
    ]);
    $this->user->organizations()->attach($this->organization);
});

it('lists projects for the current organization', function () {
    Project::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'My Project',
        'description' => 'A test project',
    ]);

    $tool = new ListProjectsTool($this->user);
    $result = json_decode($tool->handle(new Request([])), true);

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('My Project')
        ->and($result[0]['description'])->toBe('A test project');
});

it('does not list projects from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    Project::factory()->create(['organization_id' => $otherOrg->id]);

    $tool = new ListProjectsTool($this->user);
    $result = json_decode($tool->handle(new Request([])), true);

    expect($result)->toBeEmpty();
});

it('returns an empty array when no projects exist', function () {
    $tool = new ListProjectsTool($this->user);
    $result = json_decode($tool->handle(new Request([])), true);

    expect($result)->toBeEmpty();
});
