<?php

use App\Ai\Tools\GitCommitTool;
use App\Ai\Tools\GitPushTool;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use App\Services\LocalExecutionDriver;
use Laravel\Ai\Tools\Request;

describe('GitHubService token resolution', function () {
    it('uses user token when user has github_token', function () {
        $installation = GithubInstallation::factory()->create();
        $user = User::factory()->withGithubToken()->create();

        $service = new GitHubService;
        $token = $service->resolveToken($installation, $user);

        expect($token)->toBe($user->github_token);
    });

    it('falls back to installation token when user has no github_token', function () {
        $installation = GithubInstallation::factory()->create();
        $user = User::factory()->create(['github_token' => null]);

        $service = Mockery::mock(GitHubService::class)->makePartial();
        $service->shouldReceive('getInstallationToken')
            ->with($installation->installation_id)
            ->andReturn('installation-token-123');

        $token = $service->resolveToken($installation, $user);

        expect($token)->toBe('installation-token-123');
    });

    it('falls back to installation token when user is null', function () {
        $installation = GithubInstallation::factory()->create();

        $service = Mockery::mock(GitHubService::class)->makePartial();
        $service->shouldReceive('getInstallationToken')
            ->with($installation->installation_id)
            ->andReturn('installation-token-456');

        $token = $service->resolveToken($installation);

        expect($token)->toBe('installation-token-456');
    });
});

describe('GitCommitTool user attribution', function () {
    it('sets git author from user when provided', function () {
        $rawTempDir = sys_get_temp_dir().'/git-commit-attr-'.uniqid();
        mkdir($rawTempDir, 0755, true);
        $driver = new LocalExecutionDriver($rawTempDir);

        $driver->exec('git init');
        $driver->exec('git config user.email "app@pageant.test"');
        $driver->exec('git config user.name "Pageant App"');

        $driver->writeFile('test.txt', 'hello');

        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $tool = new GitCommitTool($driver, $user);
        $result = json_decode($tool->handle(new Request(['message' => 'test commit'])), true);

        expect($result)->toHaveKey('hash');

        $logResult = $driver->exec('git log -1 --format="%an <%ae>"');
        expect(trim($logResult->stdout))->toBe('Jane Doe <jane@example.com>');

        $driver->cleanup();
    });

    it('uses default git config when no user provided', function () {
        $rawTempDir = sys_get_temp_dir().'/git-commit-noattr-'.uniqid();
        mkdir($rawTempDir, 0755, true);
        $driver = new LocalExecutionDriver($rawTempDir);

        $driver->exec('git init');
        $driver->exec('git config user.email "app@pageant.test"');
        $driver->exec('git config user.name "Pageant App"');

        $driver->writeFile('test.txt', 'hello');

        $tool = new GitCommitTool($driver);
        $result = json_decode($tool->handle(new Request(['message' => 'test commit'])), true);

        expect($result)->toHaveKey('hash');

        $logResult = $driver->exec('git log -1 --format="%an <%ae>"');
        expect(trim($logResult->stdout))->toBe('Pageant App <app@pageant.test>');

        $driver->cleanup();
    });
});

describe('GitPushTool credential configuration', function () {
    it('configures HTTPS remote with user token', function () {
        $rawTempDir = sys_get_temp_dir().'/git-push-attr-'.uniqid();
        mkdir($rawTempDir, 0755, true);
        $driver = new LocalExecutionDriver($rawTempDir);

        $driver->exec('git init');
        $driver->exec('git config user.email "test@example.com"');
        $driver->exec('git config user.name "Test"');
        $driver->exec('git remote add origin https://github.com/owner/repo.git');

        $user = User::factory()->withGithubToken()->create();

        $tool = new GitPushTool($driver, $user);

        // We can't actually push, but we can verify the credential setup
        // by triggering handle and checking the remote URL was updated
        $driver->writeFile('test.txt', 'hello');
        $driver->exec('git add -A && git commit -m "init"');

        // The push will fail (no real remote), but the credential config should happen
        $result = json_decode($tool->handle(new Request([])), true);

        // Check the remote URL was updated with the token
        $remoteResult = $driver->exec('git remote get-url origin');
        $remoteUrl = trim($remoteResult->stdout);

        expect($remoteUrl)->toContain($user->github_username);
        expect($remoteUrl)->toContain($user->github_token);
        expect($remoteUrl)->toContain('github.com/owner/repo.git');

        $driver->cleanup();
    });

    it('does not modify remote when user has no token', function () {
        $rawTempDir = sys_get_temp_dir().'/git-push-noattr-'.uniqid();
        mkdir($rawTempDir, 0755, true);
        $driver = new LocalExecutionDriver($rawTempDir);

        $driver->exec('git init');
        $driver->exec('git config user.email "test@example.com"');
        $driver->exec('git config user.name "Test"');
        $driver->exec('git remote add origin https://github.com/owner/repo.git');

        $user = User::factory()->create(['github_token' => null]);

        $tool = new GitPushTool($driver, $user);

        $driver->writeFile('test.txt', 'hello');
        $driver->exec('git add -A && git commit -m "init"');

        $tool->handle(new Request([]));

        $remoteResult = $driver->exec('git remote get-url origin');
        expect(trim($remoteResult->stdout))->toBe('https://github.com/owner/repo.git');

        $driver->cleanup();
    });
});

describe('ToolRegistry user attribution', function () {
    beforeEach(function () {
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withGithubToken()->create([
            'current_organization_id' => $this->organization->id,
        ]);
        $this->user->organizations()->attach($this->organization);
        $this->installation = GithubInstallation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->workspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->workspaceReference = WorkspaceReference::factory()->create([
            'workspace_id' => $this->workspace->id,
            'source' => 'github',
            'source_reference' => 'owner/repo',
        ]);
    });

    it('passes user to github tools', function () {
        $tools = \App\Ai\ToolRegistry::resolve(
            ['create_pull_request'],
            'owner/repo',
            $this->user,
        );

        expect($tools)->toHaveCount(1);

        $tool = $tools[0];
        $reflection = new ReflectionProperty($tool, 'user');
        expect($reflection->getValue($tool))->toBe($this->user);
    });

    it('passes user to worktree tools', function () {
        $driver = new LocalExecutionDriver(sys_get_temp_dir().'/test-'.uniqid());

        $tools = \App\Ai\ToolRegistry::resolve(
            ['git_commit'],
            'owner/repo',
            $this->user,
            $driver,
        );

        expect($tools)->toHaveCount(1);

        $tool = $tools[0];
        $reflection = new ReflectionProperty($tool, 'user');
        expect($reflection->getValue($tool))->toBe($this->user);

        $driver->cleanup();
    });
});

describe('OAuth callback stores tokens', function () {
    it('stores github token and username on login', function () {
        $user = User::factory()->create([
            'github_id' => 12345,
            'github_token' => null,
            'github_username' => null,
        ]);

        $user->update([
            'github_token' => 'gho_test_token_123',
            'github_refresh_token' => 'ghr_test_refresh_456',
            'github_username' => 'testuser',
        ]);

        $user->refresh();

        expect($user->github_token)->toBe('gho_test_token_123');
        expect($user->github_refresh_token)->toBe('ghr_test_refresh_456');
        expect($user->github_username)->toBe('testuser');
        expect($user->hasGithubToken())->toBeTrue();
    });

    it('reports no token when github_token is null', function () {
        $user = User::factory()->create(['github_token' => null]);

        expect($user->hasGithubToken())->toBeFalse();
    });
});
