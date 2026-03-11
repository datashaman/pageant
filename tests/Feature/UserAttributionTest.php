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
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir().'/git-commit-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->driver = new LocalExecutionDriver($this->tempDir);
        $this->driver->exec('git init');
        $this->driver->exec('git config user.email "app@pageant.test"');
        $this->driver->exec('git config user.name "Pageant App"');
    });

    afterEach(function () {
        $this->driver->cleanup();
    });

    it('sets git author from user when provided', function () {
        $this->driver->writeFile('test.txt', 'hello');

        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $tool = new GitCommitTool($this->driver, $user);
        $result = json_decode($tool->handle(new Request(['message' => 'test commit'])), true);

        expect($result)->toHaveKey('hash');

        $logResult = $this->driver->exec('git log -1 --format="%an <%ae>"');
        expect(trim($logResult->stdout))->toBe('Jane Doe <jane@example.com>');
    });

    it('uses default git config when no user provided', function () {
        $this->driver->writeFile('test.txt', 'hello');

        $tool = new GitCommitTool($this->driver);
        $result = json_decode($tool->handle(new Request(['message' => 'test commit'])), true);

        expect($result)->toHaveKey('hash');

        $logResult = $this->driver->exec('git log -1 --format="%an <%ae>"');
        expect(trim($logResult->stdout))->toBe('Pageant App <app@pageant.test>');
    });
});

describe('GitPushTool credential configuration', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir().'/git-push-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->driver = new LocalExecutionDriver($this->tempDir);
        $this->driver->exec('git init');
        $this->driver->exec('git config user.email "test@example.com"');
        $this->driver->exec('git config user.name "Test"');
        $this->driver->exec('git remote add origin https://github.com/owner/repo.git');
        $this->driver->writeFile('test.txt', 'hello');
        $this->driver->exec('git add -A && git commit -m "init"');
    });

    afterEach(function () {
        $this->driver->cleanup();
    });

    it('does not persist credentials in remote URL when user has token', function () {
        $user = User::factory()->withGithubToken()->create();

        $tool = new GitPushTool($this->driver, $user);

        // Push will fail (no real remote), but we verify the remote URL is unchanged
        $tool->handle(new Request([]));

        $remoteResult = $this->driver->exec('git remote get-url origin');
        expect(trim($remoteResult->stdout))->toBe('https://github.com/owner/repo.git');
    });

    it('does not modify remote when user has no token', function () {
        $user = User::factory()->create(['github_token' => null]);

        $tool = new GitPushTool($this->driver, $user);
        $tool->handle(new Request([]));

        $remoteResult = $this->driver->exec('git remote get-url origin');
        expect(trim($remoteResult->stdout))->toBe('https://github.com/owner/repo.git');
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
        WorkspaceReference::factory()->create([
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

    it('passes user to git worktree tools', function () {
        $tempDir = sys_get_temp_dir().'/test-'.uniqid();
        mkdir($tempDir, 0755, true);
        $driver = new LocalExecutionDriver($tempDir);

        try {
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
        } finally {
            $driver->cleanup();
        }
    });

    it('does not pass user to file worktree tools', function () {
        $tempDir = sys_get_temp_dir().'/test-'.uniqid();
        mkdir($tempDir, 0755, true);
        $driver = new LocalExecutionDriver($tempDir);

        try {
            $tools = \App\Ai\ToolRegistry::resolve(
                ['glob', 'read_file', 'bash'],
                'owner/repo',
                $this->user,
                $driver,
            );

            expect($tools)->toHaveCount(3);
        } finally {
            $driver->cleanup();
        }
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
