<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PageantAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function stream(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
            'repo_full_name' => ['nullable', 'string'],
            'page_context' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $assistant = new PageantAssistant(
            user: $user,
            repoFullName: $request->input('repo_full_name'),
            pageContext: $request->input('page_context', ''),
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
