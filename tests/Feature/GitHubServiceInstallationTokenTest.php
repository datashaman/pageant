<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $this->privateKeyPem);

    config([
        'services.github.app_id' => '12345',
        'services.github.private_key_path' => $this->privateKeyPem,
    ]);
});

it('listAppInstallations uses JWT auth, not user tokens', function () {
    Http::fake([
        'https://api.github.com/app/installations*' => Http::response([
            ['id' => 1001, 'account' => ['login' => 'acme', 'type' => 'Organization'], 'permissions' => [], 'events' => []],
        ]),
    ]);

    $service = new GitHubService;
    $installations = $service->listAppInstallations();

    expect($installations)->toHaveCount(1);
    expect($installations[0]['id'])->toBe(1001);
    expect($installations[0]['account']['login'])->toBe('acme');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/app/installations')
            && str_starts_with($request->header('Authorization')[0], 'Bearer ey');
    });
});

it('getInstallationToken uses JWT auth, not user tokens', function () {
    Http::fake([
        'https://api.github.com/app/installations/999/access_tokens' => Http::response([
            'token' => 'ghs_installation_token_abc123',
            'expires_at' => now()->addHour()->toISOString(),
        ]),
    ]);

    $service = new GitHubService;
    $token = $service->getInstallationToken(999);

    expect($token)->toBe('ghs_installation_token_abc123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/app/installations/999/access_tokens')
            && str_starts_with($request->header('Authorization')[0], 'Bearer ey');
    });
});

it('uses installation tokens for all repo API calls', function () {
    $organization = Organization::factory()->create();
    $installation = GithubInstallation::factory()->create([
        'organization_id' => $organization->id,
        'installation_id' => 555,
    ]);

    Http::fake([
        'https://api.github.com/app/installations/555/access_tokens' => Http::response([
            'token' => 'ghs_install_token',
            'expires_at' => now()->addHour()->toISOString(),
        ]),
        'https://api.github.com/repos/acme/widgets/issues' => Http::response([
            'number' => 1,
            'title' => 'Test issue',
            'state' => 'open',
        ]),
    ]);

    $service = new GitHubService;
    $service->createIssue($installation, 'acme/widgets', ['title' => 'Test issue']);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/repos/acme/widgets/issues')) {
            return $request->header('Authorization')[0] === 'Bearer ghs_install_token';
        }

        return true;
    });
});

it('user model no longer exposes github_token fields', function () {
    $user = new \App\Models\User;

    expect($user->getFillable())->not->toContain('github_token');
    expect($user->getFillable())->not->toContain('github_refresh_token');
});
