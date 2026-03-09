<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Repo;
use App\Services\WebhookRelevanceFilter;
use Laravel\Ai\AnonymousAgent;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
    $this->agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'push', 'filters' => []]],
        'provider' => 'anthropic',
        'secondary_model' => 'cheapest',
    ]);
    $this->agent->repos()->attach($this->repo);
});

it('returns relevant when the model responds YES', function () {
    AnonymousAgent::fake([
        "YES\nThis push event matches the agent's subscribed events.",
    ]);

    $filter = app(WebhookRelevanceFilter::class);
    $result = $filter->isRelevant(
        $this->agent,
        'Push to refs/heads/main with 2 commits',
        'acme/widgets',
    );

    expect($result['relevant'])->toBeTrue();
    expect($result['reason'])->toContain('push event');
});

it('returns not relevant when the model responds NO', function () {
    AnonymousAgent::fake([
        "NO\nThis event is for a different repository.",
    ]);

    $filter = app(WebhookRelevanceFilter::class);
    $result = $filter->isRelevant(
        $this->agent,
        'Push to refs/heads/main',
        'other/repo',
    );

    expect($result['relevant'])->toBeFalse();
    expect($result['reason'])->toContain('different repository');
});

it('defaults to relevant when the model call fails', function () {
    AnonymousAgent::fake(function () {
        throw new \RuntimeException('API unavailable');
    });

    $filter = app(WebhookRelevanceFilter::class);
    $result = $filter->isRelevant(
        $this->agent,
        'Push to refs/heads/main',
        'acme/widgets',
    );

    expect($result['relevant'])->toBeTrue();
    expect($result['reason'])->toContain('defaulting to relevant');
});

it('constructs the prompt with agent and event context', function () {
    AnonymousAgent::fake([
        "YES\nRelevant event.",
    ]);

    $filter = app(WebhookRelevanceFilter::class);
    $filter->isRelevant(
        $this->agent,
        'Push event payload here',
        'acme/widgets',
    );

    AnonymousAgent::assertPrompted(function ($prompt) {
        return $prompt->contains('Push event payload here')
            && $prompt->contains('acme/widgets');
    });
});
