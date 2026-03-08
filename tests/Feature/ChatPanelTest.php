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
    PageantAssistant::fake(['Hello! I can help with general questions.']);

    $orgWithoutRepo = Organization::factory()->create();
    $user = User::factory()->create([
        'current_organization_id' => $orgWithoutRepo->id,
    ]);
    $user->organizations()->attach($orgWithoutRepo);

    $response = $this->actingAs($user)
        ->post(route('chat.stream'), [
            'message' => 'Hello',
        ]);

    $response->assertOk();
    $response->assertHeader('content-type', 'text/event-stream; charset=utf-8');
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

it('includes proactive behavior directives in instructions', function () {
    $assistant = new PageantAssistant(
        user: $this->user,
        repoFullName: 'acme/widgets',
    );

    $instructions = $assistant->instructions();

    expect($instructions)
        ->toContain('Act immediately when the user\'s intent is clear')
        ->toContain('destructive or irreversible action')
        ->toContain('Batch related operations together')
        ->toContain('context narrows to a single option')
        ->toContain('genuine ambiguity');
});

it('includes rich page context in assistant instructions', function () {
    $context = 'User is viewing a work item. work item id: abc-123. work item title: Fix login bug. project: My Project';

    $assistant = new PageantAssistant(
        user: $this->user,
        pageContext: $context,
    );

    expect($assistant->instructions())
        ->toContain('Fix login bug')
        ->toContain('work item id: abc-123')
        ->toContain('My Project');
});

it('sends page context to the stream endpoint', function () {
    PageantAssistant::fake(['Got it, you are viewing a work item.']);

    $response = $this->actingAs($this->user)
        ->post(route('chat.stream'), [
            'message' => 'What am I looking at?',
            'page_context' => 'User is viewing a work item. work item id: abc-123. work item title: Fix login bug',
        ]);

    $response->assertOk();
    $response->assertHeader('content-type', 'text/event-stream; charset=utf-8')
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

it('emits conversation_id in the SSE stream', function () {
    PageantAssistant::fake(['Hello!']);

    $response = $this->actingAs($this->user)
        ->post(route('chat.stream'), [
            'message' => 'Hello',
        ]);

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toContain('conversation_id');

    // Extract the conversation_id from the SSE data
    preg_match('/data: (\{"conversation_id":"[^"]+"\})/', $content, $matches);
    expect($matches)->not->toBeEmpty();

    $data = json_decode($matches[1], true);
    expect($data['conversation_id'])->not->toBeNull();

    // Verify the conversation was stored in the database
    $this->assertDatabaseHas('agent_conversations', [
        'id' => $data['conversation_id'],
    ]);
});

it('maintains conversation context across messages', function () {
    PageantAssistant::fake(['First response.', 'Second response with context.']);

    // First message - no conversation_id
    $response = $this->actingAs($this->user)
        ->post(route('chat.stream'), [
            'message' => 'Hello',
        ]);

    $content = $response->streamedContent();
    preg_match('/data: \{"conversation_id":"([^"]+)"\}/', $content, $matches);
    expect($matches)->not->toBeEmpty();
    $conversationId = $matches[1];

    // Second message - with conversation_id
    $response = $this->actingAs($this->user)
        ->post(route('chat.stream'), [
            'message' => 'Follow up',
            'conversation_id' => $conversationId,
        ]);

    $response->assertOk();

    // All messages should be stored in the same conversation
    $messageCount = \Illuminate\Support\Facades\DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->count();

    // At least the first pair + second pair should share the same conversation
    expect($messageCount)->toBeGreaterThanOrEqual(2);

    // Verify the second response also emits the same conversation_id
    $content = $response->streamedContent();
    expect($content)->toContain('"conversation_id":"'.$conversationId.'"');
});

it('does not return conversation messages for another user', function () {
    $store = resolve(\Laravel\Ai\Contracts\ConversationStore::class);
    $conversationId = $store->storeConversation($this->user->id, 'Test chat');

    \Illuminate\Support\Facades\DB::table('agent_conversation_messages')->insert([
        'id' => \Illuminate\Support\Str::uuid7()->toString(),
        'conversation_id' => $conversationId,
        'user_id' => $this->user->id,
        'agent' => PageantAssistant::class,
        'role' => 'user',
        'content' => 'Secret message',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->getJson(route('chat.messages', ['conversation_id' => $conversationId]))
        ->assertOk()
        ->assertJsonCount(0);
});
