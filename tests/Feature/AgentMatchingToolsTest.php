<?php

use App\Ai\ToolRegistry;
use App\Ai\Tools\CreateSkillTool;
use App\Ai\Tools\ListAgentsTool;
use App\Ai\Tools\ListSkillsTool;
use App\Ai\Tools\SearchAgentsTool;
use App\Ai\Tools\SearchSkillsTool;
use App\Mcp\Servers\PageantServer;
use App\Mcp\Tools\AttachSkillToAgentTool as McpAttachSkillToAgentTool;
use App\Mcp\Tools\CreateSkillTool as McpCreateSkillTool;
use App\Mcp\Tools\ListAgentsTool as McpListAgentsTool;
use App\Mcp\Tools\ListSkillsTool as McpListSkillsTool;
use App\Mcp\Tools\SearchAgentsTool as McpSearchAgentsTool;
use App\Mcp\Tools\SearchSkillsTool as McpSearchSkillsTool;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create([
        'current_organization_id' => $this->organization->id,
    ]);
    $this->user->organizations()->attach($this->organization);
});

it('registers search_agents in the AI ToolRegistry', function () {
    $available = ToolRegistry::available();

    expect($available)->toHaveKey('search_agents')
        ->and($available)->toHaveKey('search_skills')
        ->and($available)->toHaveKey('create_skill');
});

it('lists agents with optional search via AI tool', function () {
    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'review-bot',
        'description' => 'Reviews pull requests',
    ]);
    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'deploy-bot',
        'description' => 'Deploys applications',
    ]);

    $tool = new ListAgentsTool($this->user);

    $result = json_decode($tool->handle(new Request(['search' => 'review'])), true);
    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('review-bot');

    $allResult = json_decode($tool->handle(new Request([])), true);
    // +1 for the auto-created planning agent from the observer
    expect($allResult)->toHaveCount(3);
})->skip('Requires Repo model - deferred to follow-up PR');

it('searches agents by query via AI tool', function () {
    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'code-reviewer',
        'description' => 'Reviews code and pull requests',
    ]);
    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'issue-triager',
        'description' => 'Triages incoming issues',
    ]);

    $tool = new SearchAgentsTool($this->user);
    $result = json_decode($tool->handle(new Request(['query' => 'review'])), true);

    expect($result['count'])->toBe(1)
        ->and($result['agents'][0]['name'])->toBe('code-reviewer');
})->skip('Requires Repo model - deferred to follow-up PR');

it('searches agents by tools via AI tool', function () {
    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'pr-bot',
        'tools' => ['create_comment', 'get_pull_request'],
    ]);
    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'issue-bot',
        'tools' => ['create_issue', 'list_issues'],
    ]);

    $tool = new SearchAgentsTool($this->user);
    $result = json_decode($tool->handle(new Request(['tools' => ['create_comment']])), true);

    expect($result['count'])->toBe(1)
        ->and($result['agents'][0]['name'])->toBe('pr-bot');
})->skip('Requires Repo model - deferred to follow-up PR');

it('searches agents by skills via AI tool', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'laravel-bot',
    ]);
    $skill = Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'laravel-testing',
        'description' => 'Writes Laravel tests',
    ]);
    $agent->skills()->attach($skill);

    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'generic-bot',
    ]);

    $tool = new SearchAgentsTool($this->user);
    $result = json_decode($tool->handle(new Request(['skills' => ['laravel']])), true);

    expect($result['count'])->toBe(1)
        ->and($result['agents'][0]['name'])->toBe('laravel-bot');
})->skip('Requires Repo model - deferred to follow-up PR');

it('creates a skill via AI tool', function () {
    $tool = new CreateSkillTool($this->user);
    $result = json_decode($tool->handle(new Request([
        'name' => 'code-review',
        'description' => 'Reviews code for quality',
        'provider' => 'anthropic',
    ])), true);

    expect($result['name'])->toBe('code-review')
        ->and($result['description'])->toBe('Reviews code for quality')
        ->and($result['organization_id'])->toBe($this->organization->id);

    expect(Skill::where('name', 'code-review')->exists())->toBeTrue();
});

it('lists skills with optional search via AI tool', function () {
    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'php-testing',
        'description' => 'Tests PHP applications',
    ]);
    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'js-linting',
        'description' => 'Lints JavaScript code',
    ]);

    $tool = new ListSkillsTool($this->user);

    $result = json_decode($tool->handle(new Request(['search' => 'php'])), true);
    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('php-testing');
});

it('searches skills by query via AI tool', function () {
    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'review-skill',
        'description' => 'Provides code review capabilities',
    ]);
    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'deploy-skill',
        'description' => 'Deployment automation',
    ]);

    $tool = new SearchSkillsTool($this->user);
    $result = json_decode($tool->handle(new Request(['query' => 'review'])), true);

    expect($result['count'])->toBe(1)
        ->and($result['skills'][0]['name'])->toBe('review-skill');
});

it('searches skills by allowed tools via AI tool', function () {
    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'pr-skill',
        'allowed_tools' => ['create_comment', 'get_pull_request'],
    ]);
    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'issue-skill',
        'allowed_tools' => ['create_issue'],
    ]);

    $tool = new SearchSkillsTool($this->user);
    $result = json_decode($tool->handle(new Request(['allowed_tools' => ['create_comment']])), true);

    expect($result['count'])->toBe(1)
        ->and($result['skills'][0]['name'])->toBe('pr-skill');
});

it('lists agents via MCP tool', function () {
    $this->actingAs($this->user);

    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'mcp-agent',
        'description' => 'An MCP agent',
    ]);

    $response = PageantServer::tool(McpListAgentsTool::class, []);

    $response->assertOk()
        ->assertSee('mcp-agent');
})->skip('Requires Repo model - deferred to follow-up PR');

it('lists agents filtered by search via MCP tool', function () {
    $this->actingAs($this->user);

    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'deploy-bot',
    ]);
    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'review-bot',
    ]);

    $response = PageantServer::tool(McpListAgentsTool::class, [
        'search' => 'deploy',
    ]);

    $response->assertOk()
        ->assertSee('deploy-bot');
})->skip('Requires Repo model - deferred to follow-up PR');

it('searches agents via MCP tool', function () {
    $this->actingAs($this->user);

    Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'search-target',
        'description' => 'A specialized review agent',
    ]);

    $response = PageantServer::tool(McpSearchAgentsTool::class, [
        'query' => 'review',
    ]);

    $response->assertOk()
        ->assertSee('search-target');
})->skip('Requires Repo model - deferred to follow-up PR');

it('lists skills via MCP tool', function () {
    $this->actingAs($this->user);

    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'mcp-skill',
    ]);

    $response = PageantServer::tool(McpListSkillsTool::class, []);

    $response->assertOk()
        ->assertSee('mcp-skill');
});

it('searches skills via MCP tool', function () {
    $this->actingAs($this->user);

    Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'review-skill',
        'description' => 'Code review capabilities',
    ]);

    $response = PageantServer::tool(McpSearchSkillsTool::class, [
        'query' => 'review',
    ]);

    $response->assertOk()
        ->assertSee('review-skill');
});

it('creates a skill via MCP tool', function () {
    $this->actingAs($this->user);

    $response = PageantServer::tool(McpCreateSkillTool::class, [
        'name' => 'mcp-new-skill',
        'description' => 'A skill created via MCP',
        'provider' => 'anthropic',
    ]);

    $response->assertOk()
        ->assertSee('mcp-new-skill');

    expect(Skill::where('name', 'mcp-new-skill')->exists())->toBeTrue();
});

it('attaches a skill to an agent via MCP tool', function () {
    $this->actingAs($this->user);

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'target-agent',
    ]);
    $skill = Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'target-skill',
    ]);

    $response = PageantServer::tool(McpAttachSkillToAgentTool::class, [
        'agent_id' => $agent->id,
        'skill_id' => $skill->id,
    ]);

    $response->assertOk()
        ->assertSee('target-skill')
        ->assertSee('target-agent');

    expect($agent->fresh()->skills->pluck('id'))->toContain($skill->id);
});
