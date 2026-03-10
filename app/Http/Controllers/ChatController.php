<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PageantAssistant;
use App\Models\UserApiKey;
use App\Models\WorkspaceReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\ConversationStore;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
            'page_context' => ['nullable', 'string', 'max:2000'],
            'model' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $contextData = is_array($decoded = json_decode($request->input('page_context', '{}'), true)) ? $decoded : [];
        $repoFullName = self::resolveRepoFullName($contextData, $user);
        $pageContext = self::formatPageContext($contextData);

        $assistant = new PageantAssistant(
            user: $user,
            repoFullName: $repoFullName,
            pageContext: $pageContext,
        );

        if ($conversationId = $request->input('conversation_id')) {
            $ownsConversation = DB::table('agent_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $user->id)
                ->exists();

            if (! $ownsConversation) {
                abort(403, 'You are not allowed to access this conversation.');
            }

            $assistant->resumeConversation($conversationId);
        }

        $message = $request->input('message');

        $this->ensureConversationExists($assistant, $user, $message);

        [$streamProvider, $streamModel] = $this->resolveModelSelection($request->input('model'));

        $providerToInject = $streamProvider ?? config('ai.default', 'anthropic');
        $originalKey = config("ai.providers.{$providerToInject}.key");

        $userApiKey = UserApiKey::query()
            ->where('user_id', $user->id)
            ->where('provider', $providerToInject)
            ->valid()
            ->first();

        if ($userApiKey) {
            config(["ai.providers.{$providerToInject}.key" => $userApiKey->api_key]);
        }

        $streamable = $assistant->stream($message, provider: $streamProvider, model: $streamModel);

        return response()->stream(function () use ($assistant, $user, $streamable, $providerToInject, $originalKey) {
            $flush = function () {
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $fullText = '';
            $toolCalls = [];
            $toolResults = [];

            try {
                foreach ($streamable as $event) {
                    $decoded = json_decode((string) $event, true);

                    if (is_array($decoded)) {
                        $eventType = $decoded['type'] ?? '';

                        if ($eventType === 'text_delta') {
                            $fullText .= $decoded['delta'] ?? '';
                        } elseif ($eventType === 'tool_call') {
                            $toolCalls[] = [
                                'id' => $decoded['tool_id'] ?? '',
                                'name' => $decoded['tool_name'] ?? '',
                                'arguments' => $decoded['arguments'] ?? [],
                            ];
                        } elseif ($eventType === 'tool_result') {
                            $toolResults[] = [
                                'id' => $decoded['tool_id'] ?? '',
                                'name' => $decoded['tool_name'] ?? '',
                                'result' => $decoded['result'] ?? null,
                                'arguments' => $decoded['arguments'] ?? [],
                            ];
                        }
                    }

                    echo 'data: '.((string) $event)."\n\n";
                    $flush();
                }
            } catch (\Throwable $e) {
                report($e);

                $errorMessage = 'Sorry, something went wrong while processing your request. Please try again.';
                echo 'data: '.json_encode(['type' => 'text_delta', 'delta' => $errorMessage])."\n\n";
                $flush();

                $fullText .= $errorMessage;
            } finally {
                if (($fullText !== '' || $toolCalls !== [] || $toolResults !== []) && $conversationId = $assistant->currentConversation()) {
                    $this->storeAssistantMessage($conversationId, $user->id, $fullText, $toolCalls, $toolResults);
                }
            }

            if ($conversationId = $assistant->currentConversation()) {
                echo 'data: '.json_encode(['conversation_id' => $conversationId])."\n\n";
                $flush();
            }

            echo "data: [DONE]\n\n";
            $flush();

            config(["ai.providers.{$providerToInject}.key" => $originalKey]);
        }, headers: ['Content-Type' => 'text/event-stream']);
    }

    /**
     * Eagerly create the conversation and persist the user message before streaming begins.
     *
     * This guarantees the user message is never lost, even when the SSE stream
     * errors mid-way and the framework's RememberConversation middleware
     * never fires its .then() callback.
     */
    protected function ensureConversationExists(PageantAssistant $assistant, \App\Models\User $user, string $message): void
    {
        if (! $assistant->currentConversation()) {
            $conversationId = resolve(ConversationStore::class)->storeConversation(
                $user->id,
                Str::limit($message, 100, preserveWords: true),
            );

            $assistant->resumeConversation($conversationId);
        }

        $conversationId = $assistant->currentConversation();

        DB::table('agent_conversation_messages')->insert([
            'id' => Str::uuid7()->toString(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => PageantAssistant::class,
            'role' => 'user',
            'content' => $message,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->update(['updated_at' => now()]);
    }

    /**
     * Store the assistant response captured from the stream.
     *
     * Called from a finally block so partial content is preserved even on error.
     *
     * @param  array<int, array{id: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  array<int, array{id: string, name: string, result: mixed, arguments: array<string, mixed>}>  $toolResults
     */
    protected function storeAssistantMessage(string $conversationId, int $userId, string $text, array $toolCalls = [], array $toolResults = []): void
    {
        DB::table('agent_conversation_messages')->insert([
            'id' => Str::uuid7()->toString(),
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'agent' => PageantAssistant::class,
            'role' => 'assistant',
            'content' => $text,
            'attachments' => '[]',
            'tool_calls' => json_encode($toolCalls),
            'tool_results' => json_encode($toolResults),
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->update(['updated_at' => now()]);
    }

    /**
     * Resolve a GitHub repo full name from the structured page context,
     * scoped to the authenticated user's organizations.
     *
     * @param  array<string, mixed>  $context
     */
    public static function resolveRepoFullName(array $context, \App\Models\User $user): ?string
    {
        $candidate = null;

        // Workspace page: source_reference may contain repo or issue ref
        if (! empty($context['source_reference']) && ($context['source'] ?? '') === 'github') {
            $candidate = Str::before($context['source_reference'], '#');
        }

        // Legacy repo page context: repo_source_reference
        if (! $candidate && ! empty($context['repo_source_reference']) && ($context['repo_source'] ?? '') === 'github') {
            $candidate = Str::before($context['repo_source_reference'], '#');
        }

        if (! $candidate) {
            return null;
        }

        // Verify a workspace reference exists for this repo in one of the user's organizations
        $userOrgIds = $user->organizations()->pluck('organizations.id');

        $exists = WorkspaceReference::where('source', 'github')
            ->where(function ($query) use ($candidate) {
                $query->where('source_reference', $candidate)
                    ->orWhere('source_reference', 'LIKE', $candidate.'#%');
            })
            ->whereHas('workspace', fn ($q) => $q->whereIn('organization_id', $userOrgIds))
            ->exists();

        return $exists ? $candidate : null;
    }

    /** @var list<string> */
    private const CONTEXT_DISPLAY_KEYS = [
        'workspace_id', 'workspace_name',
        'source', 'source_reference',
        'repo_id', 'repo_name', 'repo_source', 'repo_source_reference',
        'agent_id', 'agent_name', 'agent_description',
        'skill_id', 'skill_name',
    ];

    /**
     * Format structured page context into a readable string for the assistant.
     *
     * @param  array<string, mixed>  $context
     */
    public static function formatPageContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $page = $context['page'] ?? '';
        $parts = explode('.', $page);
        $resource = $parts[0] ?? '';
        $action = $parts[1] ?? '';
        $singular = $resource ? rtrim(str_replace('-', ' ', $resource), 's') : '';

        $lines = [];

        if (in_array($action, ['show', 'edit'])) {
            $verb = $action === 'show' ? 'viewing' : 'editing';
            $lines[] = "User is {$verb} a {$singular}";

            foreach (self::CONTEXT_DISPLAY_KEYS as $key) {
                if (isset($context[$key]) && $context[$key] !== '') {
                    $label = str_replace('_', ' ', $key);
                    $lines[] = "{$label}: {$context[$key]}";
                }
            }
        } elseif ($action === 'create') {
            $lines[] = "User is on the {$singular} creation page";
        } elseif ($action === 'index') {
            $lines[] = 'User is on the '.str_replace('-', ' ', $resource).' list page';
        } elseif ($page) {
            $lines[] = "User is on the {$page}";
        } else {
            $lines[] = 'User is on the dashboard';
        }

        return implode('. ', $lines);
    }

    /**
     * Resolve the provider and model from the model selection string.
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function resolveModelSelection(?string $selection): array
    {
        if (! $selection) {
            return [null, null];
        }

        if (in_array($selection, ['cheapest', 'smartest'])) {
            $defaultProvider = config('ai.default', 'anthropic');
            $provider = Ai::textProviderFor(new PageantAssistant(auth()->user()), $defaultProvider);

            $model = match ($selection) {
                'cheapest' => $provider->cheapestTextModel(),
                'smartest' => $provider->smartestTextModel(),
            };

            return [$defaultProvider, $model];
        }

        if (str_contains($selection, ':')) {
            [$provider, $model] = explode(':', $selection, 2);

            return [$provider, $model];
        }

        return [null, $selection];
    }

    public function messages(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => ['required', 'string', 'max:36'],
        ]);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $request->input('conversation_id'))
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get(['role', 'content', 'tool_calls', 'tool_results'])
            ->map(function ($message) {
                $message->tool_calls = json_decode($message->tool_calls, true) ?: [];
                $message->tool_results = json_decode($message->tool_results, true) ?: [];

                return $message;
            });

        return response()->json($messages);
    }
}
