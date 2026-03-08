<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\User;
use App\Services\GitHubService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->user->organizations()->attach($this->organization);

    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('shows the setup script field on the edit page', function () {
    $this->actingAs($this->user)
        ->get(route('repos.edit', $this->repo))
        ->assertOk()
        ->assertSee('Setup Script');
});

it('displays setup script on the show page', function () {
    $this->repo->update(['setup_script' => "#!/bin/bash\ncomposer install"]);

    $this->actingAs($this->user)
        ->get(route('repos.show', $this->repo))
        ->assertOk()
        ->assertSee('Setup Script')
        ->assertSee('composer install');
});

it('does not display setup script section when empty', function () {
    $this->actingAs($this->user)
        ->get(route('repos.show', $this->repo))
        ->assertOk()
        ->assertDontSee('Setup Script');
});

it('stores and retrieves setup script as a model attribute', function () {
    $script = "#!/bin/bash\napt-get update\ncomposer install --no-dev";

    $repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'setup_script' => $script,
    ]);

    expect($repo->fresh()->setup_script)->toBe($script);
});

it('allows null setup script', function () {
    $repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'setup_script' => null,
    ]);

    expect($repo->fresh()->setup_script)->toBeNull();
});

it('saves setup script via the edit form', function () {
    $script = "#!/bin/bash\ncomposer install\nnpm ci";

    Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $this->repo])
        ->set('setupScript', $script)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($this->repo->fresh()->setup_script)->toBe($script);
});

it('suggests setup script from copilot setup steps file', function () {
    $this->mock(GitHubService::class, function ($mock) {
        $mock->shouldReceive('getFileContents')
            ->with(\Mockery::any(), 'acme/widgets', '.github/copilot-setup-steps.yml')
            ->andReturn("name: Setup\nsteps:\n  - run: composer install");

        $mock->shouldReceive('getFileContents')
            ->with(\Mockery::any(), 'acme/widgets', 'codex-setup.sh')
            ->andThrow(new RequestException(new Response(new \GuzzleHttp\Psr7\Response(404))));

        $mock->shouldReceive('getFileContents')
            ->with(\Mockery::any(), 'acme/widgets', '.devcontainer/devcontainer.json')
            ->andThrow(new RequestException(new Response(new \GuzzleHttp\Psr7\Response(404))));
    });

    Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $this->repo])
        ->call('suggestSetupScript')
        ->assertSet('setupScript', "# From .github/copilot-setup-steps.yml\n\nname: Setup\nsteps:\n  - run: composer install");
});

it('shows message when no setup files found in repo', function () {
    $this->mock(GitHubService::class, function ($mock) {
        $mock->shouldReceive('getFileContents')
            ->andThrow(new RequestException(new Response(new \GuzzleHttp\Psr7\Response(404))));
    });

    Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $this->repo])
        ->call('suggestSetupScript')
        ->assertSet('setupSuggestionMessage', fn ($v) => ! empty($v));
});

it('shows message for non-github repos', function () {
    $repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'gitlab',
        'source_reference' => 'acme/widgets',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::repos.edit', ['repo' => $repo])
        ->call('suggestSetupScript')
        ->assertSet('setupSuggestionMessage', fn ($v) => ! empty($v));
});
