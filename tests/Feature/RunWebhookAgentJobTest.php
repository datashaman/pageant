<?php

use App\Ai\Agents\GitHubWebhookAgent;
use App\Jobs\RunWebhookAgent;
use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\WorkItem;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\MessageRole;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
        'installation_id' => 12345,
    ]);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('constructs GitHubWebhookAgent and calls prompt with event context', function () {
    GitHubWebhookAgent::fake(['Test response']);

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'tools' => ['create_comment'],
        'provider' => 'anthropic',
        'model' => 'inherit',
    ]);

    $eventContext = "Event: push\nRepository: acme/widgets\nRef: refs/heads/main";

    $job = new RunWebhookAgent($agent, $eventContext, 'acme/widgets');
    $job->handle(app(ConversationStore::class));

    GitHubWebhookAgent::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, 'Event: push')
            && str_contains($prompt->prompt, 'acme/widgets');
    });
});

it('creates a conversation and persists messages when work item exists', function () {
    GitHubWebhookAgent::fake(['Agent response about the issue']);

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'tools' => [],
        'provider' => 'anthropic',
        'model' => 'inherit',
    ]);

    $workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'title' => 'Fix the login bug',
    ]);

    $eventContext = "Event: issue_comment\nRepository: acme/widgets\nIssue #42: Fix the login bug";

    $job = new RunWebhookAgent($agent, $eventContext, 'acme/widgets', 42);
    $job->handle(app(ConversationStore::class));

    $workItem->refresh();

    expect($workItem->conversation_id)->not->toBeNull();

    $store = app(ConversationStore::class);
    $messages = $store->getLatestConversationMessages($workItem->conversation_id, 10);

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe(MessageRole::User)
        ->and($messages[0]->content)->toContain('issue_comment')
        ->and($messages[1]->role)->toBe(MessageRole::Assistant);
});

it('continues existing conversation when conversation_id is set', function () {
    $store = app(ConversationStore::class);
    $conversationId = $store->storeConversation(null, 'Existing conversation');

    GitHubWebhookAgent::fake(['Follow-up response']);

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'tools' => [],
        'provider' => 'anthropic',
        'model' => 'inherit',
    ]);

    $workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'title' => 'Fix the login bug',
        'conversation_id' => $conversationId,
    ]);

    $eventContext = "Event: issue_comment\nRepository: acme/widgets\nNew comment on #42";

    $job = new RunWebhookAgent($agent, $eventContext, 'acme/widgets', 42);
    $job->handle($store);

    $workItem->refresh();

    expect($workItem->conversation_id)->toBe($conversationId);

    $messages = $store->getLatestConversationMessages($conversationId, 10);

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe(MessageRole::User)
        ->and($messages[1]->role)->toBe(MessageRole::Assistant);
});

it('does not persist conversation when no work item matches', function () {
    GitHubWebhookAgent::fake(['Response']);

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'tools' => [],
        'provider' => 'anthropic',
        'model' => 'inherit',
    ]);

    $eventContext = "Event: push\nRepository: acme/widgets";

    $job = new RunWebhookAgent($agent, $eventContext, 'acme/widgets', 99);
    $job->handle(app(ConversationStore::class));

    GitHubWebhookAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Event: push'));
});

it('does not persist conversation when no issue number provided', function () {
    GitHubWebhookAgent::fake(['Response']);

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'tools' => [],
        'provider' => 'anthropic',
        'model' => 'inherit',
    ]);

    $eventContext = "Event: push\nRepository: acme/widgets";

    $job = new RunWebhookAgent($agent, $eventContext, 'acme/widgets');
    $job->handle(app(ConversationStore::class));

    GitHubWebhookAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Event: push'));
});
