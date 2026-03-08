<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\User;

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
