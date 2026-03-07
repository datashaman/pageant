<?php

use App\Models\GithubInstallation;
use App\Models\Organization;

beforeEach(function () {
    config(['services.github.webhook_secret' => 'test-secret']);
});

it('rejects requests with invalid signature', function () {
    $payload = json_encode(['action' => 'created']);

    $response = $this->postJson(
        route('webhooks.github'),
        json_decode($payload, true),
        [
            'X-GitHub-Event' => 'installation',
            'X-Hub-Signature-256' => 'sha256=invalid',
            'Content-Type' => 'application/json',
        ]
    );

    $response->assertStatus(403);
});

it('accepts requests with valid signature', function () {
    $payload = json_encode([
        'action' => 'ping',
    ]);

    $response = $this->call(
        'POST',
        route('webhooks.github'),
        [],
        [],
        [],
        [
            'HTTP_X_GITHUB_EVENT' => 'ping',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    );

    $response->assertOk();
});

it('creates github installation on installation created event', function () {
    $payload = json_encode([
        'action' => 'created',
        'installation' => [
            'id' => 12345,
            'account' => [
                'login' => 'test-org',
                'type' => 'Organization',
            ],
            'permissions' => ['issues' => 'write'],
            'events' => ['issues'],
        ],
    ]);

    $response = $this->call(
        'POST',
        route('webhooks.github'),
        [],
        [],
        [],
        [
            'HTTP_X_GITHUB_EVENT' => 'installation',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    );

    $response->assertOk();

    $installation = GithubInstallation::query()->where('installation_id', 12345)->first();

    expect($installation)->not->toBeNull()
        ->and($installation->account_login)->toBe('test-org')
        ->and($installation->permissions)->toBe(['issues' => 'write'])
        ->and($installation->events)->toBe(['issues']);

    $organization = Organization::query()->where('slug', 'test-org')->first();

    expect($organization)->not->toBeNull()
        ->and($installation->organization_id)->toBe($organization->id);
});

it('deletes github installation on installation deleted event', function () {
    $installation = GithubInstallation::factory()->create([
        'installation_id' => 99999,
    ]);

    $payload = json_encode([
        'action' => 'deleted',
        'installation' => [
            'id' => 99999,
        ],
    ]);

    $response = $this->call(
        'POST',
        route('webhooks.github'),
        [],
        [],
        [],
        [
            'HTTP_X_GITHUB_EVENT' => 'installation',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    );

    $response->assertOk();

    expect(GithubInstallation::query()->where('installation_id', 99999)->exists())->toBeFalse();
});
