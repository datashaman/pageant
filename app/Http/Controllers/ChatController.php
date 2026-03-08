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
            'page_context' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $assistant = new PageantAssistant(
            user: $user,
            pageContext: $request->input('page_context', ''),
        );

        $assistant->forUser($user);

        if ($conversationId = $request->input('conversation_id')) {
            $assistant->continue($conversationId, $user);
        }

        return $assistant->stream($request->input('message'));
    }

    public function messages(Request $request)
    {
        $request->validate([
            'conversation_id' => ['required', 'string', 'max:36'],
        ]);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $request->input('conversation_id'))
            ->orderBy('created_at')
            ->get(['role', 'content']);

        return response()->json($messages);
    }
}
