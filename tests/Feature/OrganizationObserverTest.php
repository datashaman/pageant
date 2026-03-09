<?php

use App\Models\Agent;
use App\Models\Organization;

it('creates a default planning agent when an organization is created', function () {
    $organization = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org',
    ]);

    $organization->refresh();

    expect($organization->planning_agent_id)->not->toBeNull();

    $agent = $organization->planningAgent;

    expect($agent)->toBeInstanceOf(Agent::class);
    expect($agent->name)->toBe('Planning Agent');
    expect($agent->enabled)->toBeTrue();
    expect($agent->organization_id)->toBe($organization->id);
    expect($agent->isolation)->toBe('worktree');
    expect($agent->max_turns)->toBe(20);
    expect($agent->tools)->toContain('read_file', 'glob', 'grep', 'list_directory', 'create_plan', 'add_plan_step');
    expect($agent->tools)->not->toContain('write_file', 'edit_file', 'bash', 'git_commit', 'git_push');
    expect($agent->events)->toBe([]);
});

it('does not overwrite an existing planning agent on organization creation', function () {
    $existingAgent = Agent::factory()->create();

    $organization = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org-2',
        'planning_agent_id' => $existingAgent->id,
    ]);

    expect($organization->planning_agent_id)->toBe($existingAgent->id);

    // Should still only have 1 agent in the org (the factory-created one belongs to a different org)
    expect($organization->agents()->count())->toBe(0);
});

it('creates a planning agent via factory', function () {
    $organization = Organization::factory()->create();

    $organization->refresh();

    expect($organization->planning_agent_id)->not->toBeNull();
    expect($organization->planningAgent->name)->toBe('Planning Agent');
});
