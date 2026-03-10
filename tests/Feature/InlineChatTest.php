<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\User;
use App\Models\UserApiKey;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->installation = GithubInstallation::factory()->for($this->organization)->create();
    $this->repo = Repo::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/my-repo',
    ]);
    $this->project = Project::factory()->for($this->organization)->create();
    $this->workItem = WorkItem::factory()
        ->for($this->organization)
        ->forProject($this->project)
        ->create([
            'source' => 'github',
            'source_reference' => 'org/my-repo#1',
            'source_url' => 'https://github.com/org/my-repo/issues/1',
        ]);
});

it('renders the inline chat input on the work item show page', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem])
        ->assertSeeHtml('data-inline-chat')
        ->assertSeeHtml('Ask to make changes...')
        ->assertSeeHtml('x-ref="inlineChatInput"');
});

it('renders the model selector on the work item show page', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem])
        ->assertSeeHtml('Default model')
        ->assertSeeHtml('Cheapest')
        ->assertSeeHtml('Smartest')
        ->assertSeeHtml('Claude Opus 4.6')
        ->assertSeeHtml('GPT-4.1')
        ->assertSeeHtml('Gemini 2.5 Pro');
});

it('renders the model selector with provider groups', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem])
        ->assertSeeHtml('Anthropic')
        ->assertSeeHtml('OpenAI')
        ->assertSeeHtml('Gemini');
});

it('computes available providers based on config', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    config(['ai.providers.openai.key' => '']);
    config(['ai.providers.gemini.key' => '']);

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem]);

    $providers = $component->get('availableProviders');

    expect($providers['anthropic'])->toBeTrue();
});

it('computes available providers based on user API keys', function () {
    config(['ai.providers.anthropic.key' => '']);
    config(['ai.providers.openai.key' => '']);
    config(['ai.providers.gemini.key' => '']);

    UserApiKey::factory()->valid()->create([
        'user_id' => $this->user->id,
        'provider' => 'openai',
        'api_key' => 'sk-test',
    ]);

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem]);

    $providers = $component->get('availableProviders');

    expect($providers['openai'])->toBeTrue();
});

it('loads messages when work item has a conversation_id', function () {
    $store = resolve(\Laravel\Ai\Contracts\ConversationStore::class);
    $conversationId = $store->storeConversation($this->user->id, 'Work item chat');

    \Illuminate\Support\Facades\DB::table('agent_conversation_messages')->insert([
        'id' => \Illuminate\Support\Str::uuid7()->toString(),
        'conversation_id' => $conversationId,
        'user_id' => $this->user->id,
        'agent' => 'App\\Ai\\Agents\\PageantAssistant',
        'role' => 'user',
        'content' => 'Fix the login bug',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->workItem->update(['conversation_id' => $conversationId]);

    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem->fresh()]);

    expect($component->get('messages'))->toHaveCount(1);
    expect($component->get('messages.0.content'))->toBe('Fix the login bug');
    expect($component->get('conversationId'))->toBe($conversationId);
});

it('starts with empty messages when work item has no conversation', function () {
    $component = Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem]);

    expect($component->get('messages'))->toBeEmpty();
    expect($component->get('conversationId'))->toBeNull();
});

it('renders the chat input as a textarea for multi-line messages', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem])
        ->assertSeeHtml('<textarea')
        ->assertSeeHtml('@keydown.enter.prevent')
        ->assertSeeHtml('sendMessage()')
        ->assertSeeHtml('$event.shiftKey')
        ->assertSeeHtml('@input="resizeTextarea()"');
});

it('includes the chat context data attribute for the work item', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::work-items.show', ['workItem' => $this->workItem])
        ->assertSeeHtml('data-chat-context')
        ->assertSeeHtml('work-items.show');
});
