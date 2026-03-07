<?php

use App\Events\GitHubCommentReceived;
use App\Models\GithubInstallation;
use App\Models\Organization;
use Illuminate\Support\Facades\Event;

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

it('dispatches event on issue_comment webhook', function () {
    Event::fake([GitHubCommentReceived::class]);

    $payload = json_encode([
        'action' => 'created',
        'comment' => [
            'id' => 123,
            'body' => 'Looks good to me!',
            'user' => ['login' => 'reviewer'],
        ],
        'issue' => [
            'number' => 42,
            'title' => 'Fix bug',
            'pull_request' => null,
        ],
        'repository' => [
            'full_name' => 'acme/widgets',
        ],
        'installation' => [
            'id' => 77777,
        ],
    ]);

    $response = $this->call(
        'POST',
        route('webhooks.github'),
        [],
        [],
        [],
        [
            'HTTP_X_GITHUB_EVENT' => 'issue_comment',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    );

    $response->assertOk();

    Event::assertDispatched(GitHubCommentReceived::class, function ($event) {
        return $event->action === 'created'
            && $event->comment['id'] === 123
            && $event->issue['number'] === 42
            && $event->repository['full_name'] === 'acme/widgets'
            && $event->installationId === 77777;
    });
});

it('dispatches event on pull request comment webhook', function () {
    Event::fake([GitHubCommentReceived::class]);

    $payload = json_encode([
        'action' => 'created',
        'comment' => [
            'id' => 456,
            'body' => 'Please fix the tests',
            'user' => ['login' => 'maintainer'],
        ],
        'issue' => [
            'number' => 10,
            'title' => 'Add feature',
            'pull_request' => [
                'url' => 'https://api.github.com/repos/acme/widgets/pulls/10',
            ],
        ],
        'repository' => [
            'full_name' => 'acme/widgets',
        ],
        'installation' => [
            'id' => 77777,
        ],
    ]);

    $response = $this->call(
        'POST',
        route('webhooks.github'),
        [],
        [],
        [],
        [
            'HTTP_X_GITHUB_EVENT' => 'issue_comment',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    );

    $response->assertOk();

    Event::assertDispatched(GitHubCommentReceived::class, function ($event) {
        return $event->action === 'created'
            && $event->comment['id'] === 456
            && $event->issue['pull_request'] !== null;
    });
});
