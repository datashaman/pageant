<?php

use App\Ai\Agents\GitHubWebhookAgent;
use App\Jobs\RunWebhookAgent;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Repo;
use App\Services\WebhookRelevanceFilter;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('skips the webhook agent when relevance filter returns not relevant', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'push', 'filters' => []]],
        'provider' => 'anthropic',
        'secondary_model' => 'cheapest',
    ]);
    $agent->repos()->attach($this->repo);

    $filter = Mockery::mock(WebhookRelevanceFilter::class);
    $filter->shouldReceive('isRelevant')
        ->once()
        ->andReturn(['relevant' => false, 'reason' => 'Not relevant to this agent.']);

    $this->app->instance(WebhookRelevanceFilter::class, $filter);

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($message, $context) use ($agent) {
            return $message === 'Webhook event filtered as irrelevant'
                && $context['agent_id'] === $agent->id;
        });

    GitHubWebhookAgent::fake();

    $job = new RunWebhookAgent(
        agent: $agent,
        eventContext: 'Push to refs/heads/main',
        repoFullName: 'acme/widgets',
    );

    $job->handle(
        app(\Laravel\Ai\Contracts\ConversationStore::class),
        $filter,
    );

    GitHubWebhookAgent::assertNeverPrompted();
});

it('runs the webhook agent when relevance filter returns relevant', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'push', 'filters' => []]],
        'provider' => 'anthropic',
        'secondary_model' => 'cheapest',
    ]);
    $agent->repos()->attach($this->repo);

    $filter = Mockery::mock(WebhookRelevanceFilter::class);
    $filter->shouldReceive('isRelevant')
        ->once()
        ->andReturn(['relevant' => true, 'reason' => 'Event matches subscribed events.']);

    $this->app->instance(WebhookRelevanceFilter::class, $filter);

    GitHubWebhookAgent::fake(['Agent response here.']);

    $job = new RunWebhookAgent(
        agent: $agent,
        eventContext: 'Push to refs/heads/main',
        repoFullName: 'acme/widgets',
    );

    $job->handle(
        app(\Laravel\Ai\Contracts\ConversationStore::class),
        $filter,
    );

    GitHubWebhookAgent::assertPrompted('Push to refs/heads/main');
});

it('has secondary_model defaulting to cheapest on agent', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    expect($agent->secondary_model)->toBe('cheapest');
});

it('allows setting secondary_model on agent', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'secondary_model' => 'smartest',
    ]);

    expect($agent->secondary_model)->toBe('smartest');
});
