<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PageantAssistant;
use App\Models\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $streamable = $assistant->stream($message);

        return response()->stream(function () use ($assistant, $user, $streamable) {
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

        // Repo page: repo_source_reference is the full name (e.g. "acme/widgets")
        if (! empty($context['repo_source_reference']) && ($context['repo_source'] ?? '') === 'github') {
            $candidate = $context['repo_source_reference'];
        }

        // Work item page: source_reference is "owner/repo#number"
        if (! $candidate && ! empty($context['source_reference']) && ($context['source'] ?? '') === 'github') {
            $candidate = Str::before($context['source_reference'], '#');
        }

        if (! $candidate) {
            return null;
        }

        // Verify the repo belongs to one of the user's organizations
        $userOrgIds = $user->organizations()->pluck('organizations.id');

        $exists = Repo::where('source', 'github')
            ->where('source_reference', $candidate)
            ->whereIn('organization_id', $userOrgIds)
            ->exists();

        return $exists ? $candidate : null;
    }

    /** @var list<string> */
    private const CONTEXT_DISPLAY_KEYS = [
        'repo_id', 'repo_name', 'repo_source', 'repo_source_reference',
        'work_item_id', 'work_item_title', 'work_item_description',
        'project', 'project_id', 'project_name',
        'source', 'source_reference',
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
