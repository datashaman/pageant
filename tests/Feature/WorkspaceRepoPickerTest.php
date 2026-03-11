<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
});

describe('workspace create with repo picker', function () {
    it('shows repo picker when installation exists', function () {
        $installation = GithubInstallation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mockService = Mockery::mock(GitHubService::class);
        $mockService->shouldReceive('listRepositories')
            ->with(Mockery::on(fn ($inst) => $inst->id === $installation->id))
            ->andReturn([
                ['full_name' => 'acme/repo-one', 'html_url' => 'https://github.com/acme/repo-one'],
                ['full_name' => 'acme/repo-two', 'html_url' => 'https://github.com/acme/repo-two'],
            ]);
        app()->instance(GitHubService::class, $mockService);

        Livewire\Livewire::actingAs($this->user)
            ->test('pages::workspaces.create')
            ->assertSee('acme/repo-one')
            ->assertSee('acme/repo-two')
            ->assertSee('Select a repository...');
    });

    it('falls back to manual input when no installation exists', function () {
        Livewire\Livewire::actingAs($this->user)
            ->test('pages::workspaces.create')
            ->assertSee('Reference')
            ->assertDontSee('Select a repository...');
    });

    it('creates workspace with selected repo and derives source_url', function () {
        $installation = GithubInstallation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $mockService = Mockery::mock(GitHubService::class);
        $mockService->shouldReceive('listRepositories')
            ->andReturn([
                ['full_name' => 'acme/repo-one', 'html_url' => 'https://github.com/acme/repo-one'],
            ]);
        app()->instance(GitHubService::class, $mockService);

        Livewire\Livewire::actingAs($this->user)
            ->test('pages::workspaces.create')
            ->set('name', 'Test Workspace')
            ->set('references.0.source_reference', 'acme/repo-one')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $workspace = Workspace::where('name', 'Test Workspace')->first();
        expect($workspace)->not->toBeNull();

        $ref = $workspace->references()->first();
        expect($ref->source)->toBe('github')
            ->and($ref->source_reference)->toBe('acme/repo-one')
            ->and($ref->source_url)->toBe('https://github.com/acme/repo-one');
    });

    it('creates workspace with manual input when no installation exists', function () {
        Livewire\Livewire::actingAs($this->user)
            ->test('pages::workspaces.create')
            ->set('name', 'Manual Workspace')
            ->set('references.0.source_reference', 'manual/repo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $workspace = Workspace::where('name', 'Manual Workspace')->first();
        $ref = $workspace->references()->first();
        expect($ref->source)->toBe('github')
            ->and($ref->source_reference)->toBe('manual/repo')
            ->and($ref->source_url)->toBe('https://github.com/manual/repo');
    });
});

describe('workspace edit with repo picker', function () {
    it('pre-selects existing repo references', function () {
        $installation = GithubInstallation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $workspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        WorkspaceReference::factory()->create([
            'workspace_id' => $workspace->id,
            'source' => 'github',
            'source_reference' => 'acme/repo-one',
            'source_url' => 'https://github.com/acme/repo-one',
        ]);

        $mockService = Mockery::mock(GitHubService::class);
        $mockService->shouldReceive('listRepositories')
            ->andReturn([
                ['full_name' => 'acme/repo-one', 'html_url' => 'https://github.com/acme/repo-one'],
                ['full_name' => 'acme/repo-two', 'html_url' => 'https://github.com/acme/repo-two'],
            ]);
        app()->instance(GitHubService::class, $mockService);

        $component = Livewire\Livewire::actingAs($this->user)
            ->test('pages::workspaces.edit', ['workspace' => $workspace]);

        expect($component->get('references.0.source_reference'))->toBe('acme/repo-one');
    });

    it('updates workspace reference with derived source_url', function () {
        $installation = GithubInstallation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $workspace = Workspace::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        WorkspaceReference::factory()->create([
            'workspace_id' => $workspace->id,
            'source' => 'github',
            'source_reference' => 'acme/old-repo',
            'source_url' => 'https://github.com/acme/old-repo',
        ]);

        $mockService = Mockery::mock(GitHubService::class);
        $mockService->shouldReceive('listRepositories')
            ->andReturn([
                ['full_name' => 'acme/new-repo', 'html_url' => 'https://github.com/acme/new-repo'],
            ]);
        app()->instance(GitHubService::class, $mockService);

        Livewire\Livewire::actingAs($this->user)
            ->test('pages::workspaces.edit', ['workspace' => $workspace])
            ->set('references.0.source_reference', 'acme/new-repo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $ref = $workspace->references()->first();
        expect($ref->source_reference)->toBe('acme/new-repo')
            ->and($ref->source_url)->toBe('https://github.com/acme/new-repo');
    });
});
