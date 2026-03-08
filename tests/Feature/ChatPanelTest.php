<?php

use App\Ai\Agents\PageantAssistant;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\User;

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
    $this->user = User::factory()->create([
        'current_organization_id' => $this->organization->id,
    ]);
    $this->user->organizations()->attach($this->organization);
});

it('renders the chat panel for authenticated users', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeHtml('toggle-chat-panel');
});

it('requires authentication for the stream endpoint', function () {
    $this->postJson(route('chat.stream'), [
        'message' => 'Hello',
    ])->assertUnauthorized();
});

it('requires authentication for the messages endpoint', function () {
    $this->getJson(route('chat.messages', ['conversation_id' => 'test-id']))
        ->assertUnauthorized();
});

it('validates the stream request', function () {
    $this->actingAs($this->user)
        ->postJson(route('chat.stream'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('validates the messages request', function () {
    $this->actingAs($this->user)
        ->getJson(route('chat.messages'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['conversation_id']);
});

it('streams a response without a repo', function () {
    $orgWithoutRepo = Organization::factory()->create();
    $user = User::factory()->create([
        'current_organization_id' => $orgWithoutRepo->id,
    ]);
    $user->organizations()->attach($orgWithoutRepo);

    $this->actingAs($user)
        ->postJson(route('chat.stream'), [
            'message' => 'Hello',
        ])->assertOk();
});

it('streams a response via SSE', function () {
    PageantAssistant::fake(['Hello! How can I help?']);

    $response = $this->actingAs($this->user)
        ->post(route('chat.stream'), [
            'message' => 'Hello',
            'repo_full_name' => 'acme/widgets',
        ]);

    $response->assertOk();
    $response->assertHeader('content-type', 'text/event-stream; charset=utf-8');
});

it('constructs PageantAssistant with correct instructions', function () {
    $assistant = new PageantAssistant(
        user: $this->user,
        repoFullName: 'acme/widgets',
        pageContext: 'User is on the agents index page',
    );

    expect($assistant->instructions())
        ->toContain('Pageant assistant')
        ->toContain('acme/widgets')
        ->toContain('User is on the agents index page');
});

it('resolves all tools for PageantAssistant', function () {
    $assistant = new PageantAssistant(
        user: $this->user,
        repoFullName: 'acme/widgets',
    );

    $tools = iterator_to_array($assistant->tools());

    expect($tools)->not->toBeEmpty();
});

it('returns conversation messages', function () {
    $store = resolve(\Laravel\Ai\Contracts\ConversationStore::class);
    $conversationId = $store->storeConversation($this->user->id, 'Test chat');

    \Illuminate\Support\Facades\DB::table('agent_conversation_messages')->insert([
        'id' => \Illuminate\Support\Str::uuid7()->toString(),
        'conversation_id' => $conversationId,
        'user_id' => $this->user->id,
        'agent' => PageantAssistant::class,
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('chat.messages', ['conversation_id' => $conversationId]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['role' => 'user', 'content' => 'Hello']);
});
