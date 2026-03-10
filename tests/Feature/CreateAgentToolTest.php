<?php

use App\Ai\ToolRegistry;
use App\Ai\Tools\CreateAgentTool as AiCreateAgentTool;
use App\Mcp\Servers\PageantServer;
use App\Mcp\Tools\CreateAgentTool;
use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceReference;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->organization->id]);
    $this->user->organizations()->attach($this->organization);
    $this->actingAs($this->user);
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->workspace = Workspace::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->workspaceReference = WorkspaceReference::factory()->create([
        'workspace_id' => $this->workspace->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('creates an agent via MCP tool with repo', function () {
    $response = PageantServer::tool(CreateAgentTool::class, [
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
        ->and($agent->events)->toBe([['event' => 'pull_request', 'filters' => []]])
        ->and($agent->provider)->toBe('anthropic')
        ->and($agent->enabled)->toBeTrue()
        ->and($agent->workspaces->pluck('id'))->toContain($this->workspace->id);
});

it('creates an agent via MCP tool without repo', function () {
    $response = PageantServer::tool(CreateAgentTool::class, [
        'name' => 'org-bot',
        'description' => 'Manages organization tasks',
    ]);

    $response->assertOk()
        ->assertSee('org-bot');

    $agent = Agent::where('name', 'org-bot')->first();

    expect($agent)->not->toBeNull()
        ->and($agent->organization_id)->toBe($this->organization->id)
        ->and($agent->workspaces)->toHaveCount(0);
});

it('creates an agent with subscription objects via MCP tool', function () {
    $response = PageantServer::tool(CreateAgentTool::class, [
        'repo' => 'acme/widgets',
        'name' => 'filtered-bot',
        'events' => [
            ['event' => 'issues.opened', 'filters' => ['labels' => ['bug']]],
            ['event' => 'pull_request.opened', 'filters' => ['base_branch' => 'main']],
        ],
    ]);

    $response->assertOk();

    $agent = Agent::where('name', 'filtered-bot')->first();

    expect($agent)->not->toBeNull()
        ->and($agent->events)->toBe([
            ['event' => 'issues.opened', 'filters' => ['labels' => ['bug']]],
            ['event' => 'pull_request.opened', 'filters' => ['base_branch' => 'main']],
        ]);
});

it('creates an agent with defaults via MCP tool', function () {
    $response = PageantServer::tool(CreateAgentTool::class, [
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

it('attaches additional workspaces via MCP tool', function () {
    $workspace2 = Workspace::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    WorkspaceReference::factory()->create([
        'workspace_id' => $workspace2->id,
        'source' => 'github',
        'source_reference' => 'acme/gadgets',
    ]);

    $response = PageantServer::tool(CreateAgentTool::class, [
        'repo' => 'acme/widgets',
        'name' => 'multi-repo-bot',
        'repo_names' => ['acme/gadgets'],
    ]);

    $response->assertOk();

    $agent = Agent::where('name', 'multi-repo-bot')->first();
    $workspaceIds = $agent->workspaces->pluck('id');

    expect($workspaceIds)->toContain($this->workspace->id)
        ->and($workspaceIds)->toContain($workspace2->id);
});

it('registers create_agent in the AI ToolRegistry', function () {
    $available = ToolRegistry::available();

    expect($available)->toHaveKey('create_agent');
});

it('creates an agent via AI tool without repo', function () {
    $tool = new AiCreateAgentTool($this->user);

    $result = $tool->handle(new \Laravel\Ai\Tools\Request([
        'name' => 'no-repo-bot',
        'description' => 'Agent without a repo',
    ]));

    $decoded = json_decode($result, true);

    expect($decoded['name'])->toBe('no-repo-bot')
        ->and($decoded['organization_id'])->toBe($this->organization->id)
        ->and($decoded['workspaces'])->toBeEmpty();
});

it('creates an agent via AI tool with repo attachment', function () {
    $tool = new AiCreateAgentTool($this->user);

    $result = $tool->handle(new \Laravel\Ai\Tools\Request([
        'name' => 'repo-bot',
        'repo' => 'acme/widgets',
    ]));

    $decoded = json_decode($result, true);

    expect($decoded['name'])->toBe('repo-bot')
        ->and($decoded['workspaces'])->toHaveCount(1);
});

it('returns error via AI tool when user has no organization', function () {
    $orphanUser = User::factory()->create();
    $tool = new AiCreateAgentTool($orphanUser);

    $result = $tool->handle(new \Laravel\Ai\Tools\Request([
        'name' => 'orphan-bot',
    ]));

    $decoded = json_decode($result, true);

    expect($decoded)->toHaveKey('error')
        ->and($decoded['error'])->toContain('No organization');
});
