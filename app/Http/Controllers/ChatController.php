<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PageantAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function stream(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
            'page_context' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $contextData = json_decode($request->input('page_context', '{}'), true) ?: [];
        $repoFullName = self::resolveRepoFullName($contextData);
        $pageContext = self::formatPageContext($contextData);

        $assistant = new PageantAssistant(
            user: $user,
            repoFullName: $repoFullName,
            pageContext: $pageContext,
        );

        $assistant->forUser($user);

        if ($conversationId = $request->input('conversation_id')) {
            $assistant->continue($conversationId, $user);
        }

        $streamable = $assistant->stream($request->input('message'));

        return response()->stream(function () use ($assistant, $streamable) {
            $flush = function () {
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            foreach ($streamable as $event) {
                echo 'data: '.((string) $event)."\n\n";
                $flush();
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
     * Resolve a GitHub repo full name from the structured page context.
     *
     * @param  array<string, mixed>  $context
     */
    public static function resolveRepoFullName(array $context): ?string
    {
        // Repo page: repo_source_reference is the full name (e.g. "acme/widgets")
        if (! empty($context['repo_source_reference']) && ($context['repo_source'] ?? '') === 'github') {
            return $context['repo_source_reference'];
        }

        // Work item page: source_reference is "owner/repo#number"
        if (! empty($context['source_reference']) && ($context['source'] ?? '') === 'github') {
            return Str::before($context['source_reference'], '#');
        }

        return null;
    }

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

            foreach ($context as $key => $value) {
                if ($key !== 'page' && $value !== null && $value !== '') {
                    $label = str_replace('_', ' ', $key);
                    $lines[] = "{$label}: {$value}";
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

    public function messages(Request $request)
    {
        $request->validate([
            'conversation_id' => ['required', 'string', 'max:36'],
        ]);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $request->input('conversation_id'))
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get(['role', 'content']);

        return response()->json($messages);
    }
}
