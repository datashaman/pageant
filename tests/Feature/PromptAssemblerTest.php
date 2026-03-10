<?php

use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\Skill;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use App\Services\PromptAssembler;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();

    $this->organization = Organization::factory()->create();

    $this->agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'description' => 'You are a helpful coding agent.',
        'tools' => ['create_comment', 'get_issue'],
    ]);

    $this->assembler = app(PromptAssembler::class);
});

it('assembles a minimal prompt with agent configuration and behavioral steering', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
    ]);

    expect($result)
        ->toContain('You are a helpful coding agent.')
        ->toContain('Behavioral Directives')
        ->toContain('retry up to '.PromptAssembler::RETRY_CAP.' times');
});

it('includes organization policies when present', function () {
    $this->organization->update(['policies' => 'Always follow coding standards. Never commit secrets.']);

    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization->fresh(),
    ]);

    expect($result)
        ->toContain('Organization Policies')
        ->toContain('Always follow coding standards. Never commit secrets.');
});

it('excludes organization policies when empty', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
    ]);

    expect($result)->not->toContain('Organization Policies');
});

it('includes repo context when repoFullName is provided', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
        'repoFullName' => 'acme/widgets',
    ]);

    expect($result)
        ->toContain('acme/widgets')
        ->toContain('Use the available tools to interact with the repository.');
});

it('includes repo instructions when available', function () {
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    WorkspaceReference::factory()->create([
        'workspace_id' => \App\Models\Workspace::factory()->create(['organization_id' => $this->organization->id])->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);

    $this->mock(GitHubService::class, function ($mock) {
        $mock->shouldReceive('getInstallationToken')->andReturn('fake-token');
        $mock->shouldReceive('getFileContents')
            ->andReturnUsing(function ($installation, $repo, $path) {
                if ($path === 'CLAUDE.md') {
                    return '# Project coding standards';
                }
                throw new \Illuminate\Http\Client\RequestException(
                    new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(404))
                );
            });
    });

    $assembler = app(PromptAssembler::class);

    $result = $assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
        'repoFullName' => 'acme/widgets',
    ]);

    expect($result)
        ->toContain('Repository Instructions')
        ->toContain('# Project coding standards');
});

it('includes skill instructions from enabled skills', function () {
    $skill = Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'enabled' => true,
        'context' => 'You have access to the code review skill.',
    ]);

    $this->agent->skills()->attach($skill);

    $result = $this->assembler->assemble([
        'agent' => $this->agent->fresh(),
        'organization' => $this->organization,
    ]);

    expect($result)
        ->toContain('Skills')
        ->toContain('You have access to the code review skill.');
});

it('excludes disabled skills', function () {
    $skill = Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'enabled' => false,
        'context' => 'Disabled skill context.',
    ]);

    $this->agent->skills()->attach($skill);

    $result = $this->assembler->assemble([
        'agent' => $this->agent->fresh(),
        'organization' => $this->organization,
    ]);

    expect($result)->not->toContain('Disabled skill context.');
});

it('includes execution context with plan step information', function () {
    $plan = Plan::factory()->create([
        'organization_id' => $this->organization->id,
        'summary' => 'Implement the login feature.',
        'status' => 'running',
    ]);

    $completedStep = PlanStep::factory()->completed()->create([
        'plan_id' => $plan->id,
        'agent_id' => $this->agent->id,
        'order' => 1,
        'description' => 'Create the migration',
        'result' => 'Migration created successfully',
    ]);

    $currentStep = PlanStep::factory()->create([
        'plan_id' => $plan->id,
        'agent_id' => $this->agent->id,
        'order' => 2,
        'status' => 'running',
        'description' => 'Write the controller',
    ]);

    PlanStep::factory()->create([
        'plan_id' => $plan->id,
        'agent_id' => $this->agent->id,
        'order' => 3,
        'status' => 'pending',
        'description' => 'Write tests',
    ]);

    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
        'planStep' => $currentStep,
    ]);

    expect($result)
        ->toContain('Plan Summary')
        ->toContain('Implement the login feature.')
        ->toContain('Step 2 of 3: Write the controller')
        ->toContain('Prior Steps')
        ->toContain('[DONE] Create the migration')
        ->toContain('Migration created successfully');
});

it('excludes execution context when no plan step is provided', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
    ]);

    expect($result)
        ->not->toContain('Plan Summary')
        ->not->toContain('Current Step')
        ->not->toContain('Prior Steps');
});

it('includes worktree context when worktree tools are active', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
        'activeTools' => ['read_file', 'write_file', 'git_status'],
        'worktreePath' => '/tmp/worktrees/org1/acme--widgets/work-item-1',
        'worktreeBranch' => 'pageant/work-item-1',
    ]);

    expect($result)
        ->toContain('Worktree Context')
        ->toContain('/tmp/worktrees/org1/acme--widgets/work-item-1')
        ->toContain('pageant/work-item-1');
});

it('excludes worktree context when no worktree tools are active', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
        'activeTools' => ['create_comment', 'get_issue'],
        'worktreePath' => '/tmp/worktrees/some-path',
    ]);

    expect($result)->not->toContain('Worktree Context');
});

it('excludes worktree context when worktree path is missing', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
        'activeTools' => ['read_file', 'write_file'],
    ]);

    expect($result)->not->toContain('Worktree Context');
});

it('includes extra sections when provided', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
        'extras' => ['Custom instruction: do something special.'],
    ]);

    expect($result)->toContain('Custom instruction: do something special.');
});

it('always includes behavioral steering', function () {
    $result = $this->assembler->assemble([
        'agent' => $this->agent,
        'organization' => $this->organization,
    ]);

    expect($result)
        ->toContain('Behavioral Directives')
        ->toContain('retry up to')
        ->toContain('step by step')
        ->toContain('unrecoverable error');
});

it('assembles sections in priority order', function () {
    $this->organization->update(['policies' => 'Org policy text here.']);

    $skill = Skill::factory()->create([
        'organization_id' => $this->organization->id,
        'enabled' => true,
        'context' => 'Skill context text here.',
    ]);

    $this->agent->skills()->attach($skill);

    $result = $this->assembler->assemble([
        'agent' => $this->agent->fresh(),
        'organization' => $this->organization->fresh(),
    ]);

    $policyPos = strpos($result, 'Organization Policies');
    $agentPos = strpos($result, 'You are a helpful coding agent.');
    $skillPos = strpos($result, 'Skills');
    $steeringPos = strpos($result, 'Behavioral Directives');

    expect($policyPos)->toBeLessThan($agentPos)
        ->and($agentPos)->toBeLessThan($skillPos)
        ->and($skillPos)->toBeLessThan($steeringPos);
});

it('provides assembleRepoInstructions for standalone use', function () {
    $result = $this->assembler->assembleRepoInstructions(null);

    expect($result)->toBe('');
});

it('assembleRepoInstructions returns content when repo exists', function () {
    GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    WorkspaceReference::factory()->create([
        'workspace_id' => \App\Models\Workspace::factory()->create(['organization_id' => $this->organization->id])->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);

    $this->mock(GitHubService::class, function ($mock) {
        $mock->shouldReceive('getInstallationToken')->andReturn('fake-token');
        $mock->shouldReceive('getFileContents')
            ->andReturnUsing(function ($installation, $repo, $path) {
                if ($path === 'CLAUDE.md') {
                    return '# Repo instructions';
                }
                throw new \Illuminate\Http\Client\RequestException(
                    new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(404))
                );
            });
    });

    $assembler = app(PromptAssembler::class);

    $result = $assembler->assembleRepoInstructions('acme/widgets');

    expect($result)
        ->toContain('Repository Instructions')
        ->toContain('# Repo instructions');
});
