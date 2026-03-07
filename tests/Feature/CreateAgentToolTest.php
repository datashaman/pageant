<?php

use App\Ai\ToolRegistry;
use App\Mcp\Servers\GitHubServer;
use App\Mcp\Tools\CreateAgentTool;
use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('creates an agent via MCP tool', function () {
    $response = GitHubServer::tool(CreateAgentTool::class, [
        'repo' => 'acme/widgets',
        'name' => 'review-bot',
        'description' => 'Reviews pull requests',
        'tools' => ['create_comment', 'get_pull_request'],
        'events' => ['pull_request'],
        'provider' => 'anthropic',
    ]);

    $response->assertOk()
        ->assertSee('review-bot')
        ->assertSee('Reviews pull requests');

    $agent = Agent::where('name', 'review-bot')->first();

    expect($agent)->not->toBeNull()
        ->and($agent->organization_id)->toBe($this->organization->id)
        ->and($agent->tools)->toBe(['create_comment', 'get_pull_request'])
        ->and($agent->events)->toBe(['pull_request'])
        ->and($agent->provider)->toBe('anthropic')
        ->and($agent->enabled)->toBeTrue()
        ->and($agent->repos->pluck('id'))->toContain($this->repo->id);
});

it('creates an agent with defaults via MCP tool', function () {
    $response = GitHubServer::tool(CreateAgentTool::class, [
        'repo' => 'acme/widgets',
        'name' => 'simple-bot',
    ]);

    $response->assertOk();

    $agent = Agent::where('name', 'simple-bot')->first();

    expect($agent)->not->toBeNull()
        ->and($agent->tools)->toBe([])
        ->and($agent->events)->toBe([])
        ->and($agent->provider)->toBe('anthropic')
        ->and($agent->model)->toBe('inherit')
        ->and($agent->enabled)->toBeTrue();
});

it('attaches additional repos via MCP tool', function () {
    $repo2 = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/gadgets',
    ]);

    $response = GitHubServer::tool(CreateAgentTool::class, [
        'repo' => 'acme/widgets',
        'name' => 'multi-repo-bot',
        'repo_names' => ['acme/gadgets'],
    ]);

    $response->assertOk();

    $agent = Agent::where('name', 'multi-repo-bot')->first();
    $repoIds = $agent->repos->pluck('id');

    expect($repoIds)->toContain($this->repo->id)
        ->and($repoIds)->toContain($repo2->id);
});

it('registers create_agent in the AI ToolRegistry', function () {
    $available = ToolRegistry::available();

    expect($available)->toHaveKey('create_agent');
});
