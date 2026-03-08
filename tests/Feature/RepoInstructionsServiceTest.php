<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Services\GitHubService;
use App\Services\RepoInstructionsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

function make404Exception(): RequestException
{
    $psrResponse = new \GuzzleHttp\Psr7\Response(404);

    return new RequestException(new Response($psrResponse));
}

function make500Exception(): RequestException
{
    $psrResponse = new \GuzzleHttp\Psr7\Response(500);

    return new RequestException(new Response($psrResponse));
}

beforeEach(function () {
    Cache::flush();

    $this->organization = Organization::factory()->create();

    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/my-repo',
    ]);

    $this->mock(GitHubService::class, function ($mock) {
        $mock->shouldReceive('getInstallationToken')
            ->andReturn('fake-token');

        $mock->shouldReceive('getFileContents')
            ->andReturnUsing(function ($installation, $repo, $path) {
                return $this->fakeFileContents[$path] ?? throw make404Exception();
            });
    });

    $this->fakeFileContents = [];
});

it('loads a single instruction file from the repo', function () {
    $this->fakeFileContents = [
        'CLAUDE.md' => '# My Instructions',
    ];

    $service = app(RepoInstructionsService::class);
    $result = $service->loadForRepo('acme/my-repo');

    expect($result)
        ->toContain('Repository Instructions')
        ->toContain('CLAUDE.md')
        ->toContain('# My Instructions');
});

it('loads multiple instruction files and combines them', function () {
    $this->fakeFileContents = [
        'CLAUDE.md' => 'Claude rules',
        '.github/copilot-instructions.md' => 'Copilot rules',
        'AGENTS.md' => 'Agent rules',
    ];

    $service = app(RepoInstructionsService::class);
    $result = $service->loadForRepo('acme/my-repo');

    expect($result)
        ->toContain('Claude rules')
        ->toContain('Copilot rules')
        ->toContain('Agent rules');
});

it('returns empty string when no instruction files exist', function () {
    $service = app(RepoInstructionsService::class);
    $result = $service->loadForRepo('acme/my-repo');

    expect($result)->toBe('');
});

it('returns empty string when repo is not found', function () {
    $service = app(RepoInstructionsService::class);
    $result = $service->loadForRepo('nonexistent/repo');

    expect($result)->toBe('');
});

it('returns empty string when installation is not found', function () {
    Repo::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'source' => 'github',
        'source_reference' => 'orphan/repo',
    ]);

    $service = app(RepoInstructionsService::class);
    $result = $service->loadForRepo('orphan/repo');

    expect($result)->toBe('');
});

it('caches fetched file contents', function () {
    $this->fakeFileContents = [
        'CLAUDE.md' => 'Cached content',
    ];

    $service = app(RepoInstructionsService::class);
    $service->loadForRepo('acme/my-repo');

    expect(Cache::has('repo_instructions:acme/my-repo:CLAUDE.md'))->toBeTrue();
});

it('respects the character budget', function () {
    $longContent = str_repeat('A', RepoInstructionsService::MAX_CHARS);

    $this->fakeFileContents = [
        'CLAUDE.md' => $longContent,
        '.github/copilot-instructions.md' => 'Should not appear',
    ];

    $service = app(RepoInstructionsService::class);
    $result = $service->loadForRepo('acme/my-repo');

    expect(mb_strlen($result))->toBeLessThanOrEqual(RepoInstructionsService::MAX_CHARS);
    expect($result)->not->toContain('Should not appear');
});

it('gracefully handles non-404 errors', function () {
    $this->mock(GitHubService::class, function ($mock) {
        $mock->shouldReceive('getInstallationToken')
            ->andReturn('fake-token');

        $mock->shouldReceive('getFileContents')
            ->andThrow(make500Exception());
    });

    $service = app(RepoInstructionsService::class);
    $result = $service->loadForRepo('acme/my-repo');

    expect($result)->toBe('');
});
