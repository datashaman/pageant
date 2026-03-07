<?php

use App\Ai\Agents\GitHubWebhookAgent;
use App\Ai\Tools\CreateCommentTool;
use App\Ai\Tools\GetIssueTool;
use App\Ai\Tools\GetPullRequestTool;
use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
        'installation_id' => 12345,
    ]);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('resolves instructions from the agent model description', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'description' => 'You are a helpful code review bot.',
        'tools' => [],
    ]);

    $webhookAgent = new GitHubWebhookAgent($agent, 'acme/widgets', 12345);

    expect($webhookAgent->instructions())->toContain('You are a helpful code review bot.')
        ->and($webhookAgent->instructions())->toContain('acme/widgets');
});

it('resolves tools from the agent model tools config', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'tools' => ['create_comment', 'get_issue', 'get_pull_request'],
    ]);

    $webhookAgent = new GitHubWebhookAgent($agent, 'acme/widgets', 12345);
    $tools = iterator_to_array($webhookAgent->tools());

    expect($tools)->toHaveCount(3)
        ->and($tools[0])->toBeInstanceOf(CreateCommentTool::class)
        ->and($tools[1])->toBeInstanceOf(GetIssueTool::class)
        ->and($tools[2])->toBeInstanceOf(GetPullRequestTool::class);
});

it('returns empty tools when agent has no tools configured', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'tools' => [],
    ]);

    $webhookAgent = new GitHubWebhookAgent($agent, 'acme/widgets', 12345);

    expect(iterator_to_array($webhookAgent->tools()))->toBeEmpty();
});

it('resolves provider and model from agent model', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-5-20250514',
        'tools' => [],
    ]);

    $webhookAgent = new GitHubWebhookAgent($agent, 'acme/widgets', 12345);

    expect($webhookAgent->provider())->toBe('anthropic')
        ->and($webhookAgent->model())->toBe('claude-sonnet-4-5-20250514');
});

it('returns null model when set to inherit', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'model' => 'inherit',
        'tools' => [],
    ]);

    $webhookAgent = new GitHubWebhookAgent($agent, 'acme/widgets', 12345);

    expect($webhookAgent->model())->toBeNull();
});
