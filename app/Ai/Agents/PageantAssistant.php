<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Models\User;
use App\Services\ConversationCompressor;
use App\Services\PromptAssembler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent as AgentContract;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

class PageantAssistant implements AgentContract, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    protected ?ConversationCompressor $compressor = null;

    public function __construct(
        protected User $user,
        protected ?string $repoFullName = null,
        protected string $pageContext = '',
    ) {}

    /**
     * Enable conversation compression for this assistant.
     */
    public function withCompressor(ConversationCompressor $compressor): static
    {
        $this->compressor = $compressor;

        return $this;
    }

    public function instructions(): string
    {
        return implode("\n\n", array_filter([
            'You are a helpful Pageant assistant. Pageant is a platform for managing GitHub repositories, agents, workspaces, and issues.',
            'You help users manage agents, workspaces, issues, pull requests, and other GitHub operations.',
            $this->repoFullName
                ? "You are operating on the GitHub repository: {$this->repoFullName}. Use the available tools to interact with the repository when the user asks you to perform actions."
                : 'No repository is currently selected. Some tools (like create_issue, update_issue, create_workspace_issue) accept a repo parameter directly — use them without needing to select a repo first. If the user specifies a repo, pass it as the repo parameter. If only one repo exists in the organization, use it automatically. Only ask which repo to use when there is genuine ambiguity.',
            'Be concise. Do not use emojis. Give short, direct answers.',
            'Never narrate or announce internal tool calls to the user. Do not say things like "Let me check what repos are available" or "Let me look that up first". Resolve context silently by calling the necessary tools, then present only the final result or answer. The user should never see your intermediate reasoning or tool-calling steps.',
            'Act immediately when the user\'s intent is clear — do not ask for confirmation on obvious next steps. For example, if the user says "create an issue for X", create it directly without asking "are you sure?".',
            'Exception: For any destructive or irreversible action (such as deleting workspaces or labels, performing force pushes, or merging branches/PRs), always require an explicit user instruction or confirmation before invoking the corresponding tools, even if it seems like an obvious next step.',
            'Batch related operations together. For example, when setting up a workspace, perform all logical setup steps in sequence, as long as they are non-destructive.',
            'When context narrows to a single option (one repo, one agent, one workspace, etc.), use it without asking the user to confirm the selection, except when choosing would immediately lead to a destructive or irreversible action.',
            'Only ask clarifying questions when there is genuine ambiguity that cannot be resolved from context, or when the user appears to be requesting a destructive or irreversible action and you do not yet have explicit confirmation.',
            $this->pageContext ? "Current page context: {$this->pageContext}" : null,
            $this->repoFullName ? $this->loadRepoInstructions() : null,
        ]));
    }

    protected function loadRepoInstructions(): string
    {
        try {
            return app(PromptAssembler::class)->assembleRepoInstructions($this->repoFullName);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * Overrides the default implementation to reconstruct AssistantMessage
     * and ToolResultMessage objects with full tool context, so the AI
     * retains tool interaction history across conversation turns.
     */
    public function messages(): iterable
    {
        if (! $this->conversationId) {
            return [];
        }

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $this->conversationId)
            ->orderByDesc('created_at')
            ->limit($this->maxConversationMessages())
            ->get()
            ->reverse()
            ->values()
            ->flatMap(function ($row) {
                $messages = new Collection;

                if ($row->role === 'assistant') {
                    $toolCallsData = json_decode($row->tool_calls, true) ?: [];
                    $toolCalls = (new Collection($toolCallsData))->map(fn (array $tc) => new ToolCall(
                        id: $tc['id'] ?? '',
                        name: $tc['name'] ?? '',
                        arguments: $tc['arguments'] ?? [],
                    ));

                    $messages->push(new AssistantMessage($row->content ?? '', $toolCalls));

                    $toolResultsData = json_decode($row->tool_results, true) ?: [];

                    if ($toolResultsData !== []) {
                        $toolResults = (new Collection($toolResultsData))->map(fn (array $tr) => new ToolResult(
                            id: $tr['id'] ?? '',
                            name: $tr['name'] ?? '',
                            arguments: $tr['arguments'] ?? [],
                            result: $tr['result'] ?? null,
                        ));

                        $messages->push(new ToolResultMessage($toolResults));
                    }
                } else {
                    $messages->push(new Message($row->role, $row->content));
                }

                return $messages;
            })
            ->all();

        if ($this->compressor && $this->compressor->needsCompression($messages)) {
            $messages = $this->compressor->compress($messages);
        }

        return $messages;
    }

    /**
     * Resume an existing conversation without enabling the RememberConversation middleware.
     *
     * Unlike continue(), this sets the conversation ID for loading prior messages
     * but does not set a conversation participant, so the framework's built-in
     * persistence middleware is not applied. The ChatController handles message
     * persistence directly to guarantee messages survive stream errors.
     */
    public function resumeConversation(string $conversationId): static
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    public function tools(): iterable
    {
        return ToolRegistry::resolve(
            array_keys(ToolRegistry::availableForContext($this->repoFullName)),
            $this->repoFullName,
            $this->user,
        );
    }
}
