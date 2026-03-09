<?php

use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\ConversationStore;
use Livewire\Component;

new class extends Component
{
    public ?string $conversationId = null;

    public array $messages = [];

    public array $conversations = [];

    public function mount(): void
    {
        $store = resolve(ConversationStore::class);
        $this->conversationId = $store->latestConversationId(auth()->id());

        if ($this->conversationId) {
            $this->loadMessages();
        }

        $this->loadConversations();
    }

    public function loadMessages(): void
    {
        if (! $this->conversationId) {
            $this->messages = [];

            return;
        }

        $this->messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();
    }

    public function loadConversations(): void
    {
        $this->conversations = DB::table('agent_conversations')
            ->where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at,
            ])
            ->all();
    }

    public function switchConversation(string $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->loadMessages();
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->messages = [];
    }
};
?>

<div
    x-data="{
        open: JSON.parse(localStorage.getItem('chat-panel-open') || 'false'),
        currentMessage: '',
        selectedModel: localStorage.getItem('chat-panel-model') || '',
        streaming: false,
        streamedContent: '',
        transcriptCopied: false,
        messages: @entangle('messages'),
        conversationId: @entangle('conversationId'),

        close() {
            this.open = false;
            localStorage.setItem('chat-panel-open', 'false');
            this.$dispatch('chat-panel-closed');
        },

        toggle() {
            this.open = ! this.open;
            localStorage.setItem('chat-panel-open', JSON.stringify(this.open));
            if (! this.open) {
                this.$dispatch('chat-panel-closed');
            }
            if (this.open) {
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        scrollToBottom() {
            const container = this.$refs.messageList;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        getPageContext() {
            const el = document.querySelector('[data-chat-context]');

            if (el) {
                try {
                    return el.dataset.chatContext;
                } catch (e) {
                    // Fall through to URL-based detection
                }
            }

            return JSON.stringify({ page: window.location.pathname });
        },

        async sendMessage() {
            const message = this.currentMessage.trim();
            if (! message || this.streaming) return;

            this.currentMessage = '';
            this.messages.push({ role: 'user', content: message });
            this.streaming = true;
            this.streamedContent = '';

            this.$nextTick(() => {
                this.scrollToBottom();
                this.resizeTextarea();
                this.$refs.chatInput?.focus();
            });

            const streamTimeout = 120000;
            const abortController = new AbortController();
            const timeoutId = setTimeout(() => abortController.abort(), streamTimeout);

            try {
                const response = await fetch('{{ route('chat.stream') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
                        'Accept': 'text/event-stream',
                    },
                    body: JSON.stringify({
                        message: message,
                        conversation_id: this.conversationId,
                        page_context: this.getPageContext(),
                        model: this.selectedModel || null,
                    }),
                    signal: abortController.signal,
                });

                if (! response.ok) {
                    const error = await response.json();
                    this.messages.push({ role: 'assistant', content: error.error || 'An error occurred.' });
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                const processLines = (lines) => {
                    for (const line of lines) {
                        if (! line.startsWith('data: ')) continue;

                        const data = line.slice(6);
                        if (data === '[DONE]') continue;

                        try {
                            const event = JSON.parse(data);
                            if (event.type === 'text_delta') {
                                this.streamedContent += event.delta;
                                this.$nextTick(() => this.scrollToBottom());
                            }
                            if (event.conversation_id) {
                                this.conversationId = event.conversation_id;
                            }
                        } catch (e) {
                            // Skip unparseable lines
                        }
                    }
                };

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();

                    processLines(lines);
                }

                // Process any remaining data in the buffer after the stream ends
                if (buffer.trim()) {
                    processLines([buffer]);
                }
            } catch (error) {
                if (! this.streamedContent) {
                    const errorMessage = abortController.signal.aborted
                        ? 'The response timed out. Please try again.'
                        : 'Failed to connect. Please try again.';
                    this.messages.push({ role: 'assistant', content: errorMessage });
                }
            } finally {
                clearTimeout(timeoutId);

                if (this.streamedContent) {
                    this.messages.push({ role: 'assistant', content: this.streamedContent });
                    this.streamedContent = '';
                } else if (this.conversationId) {
                    // No streamed content received — reload messages from server
                    // to recover assistant replies persisted during tool-call flows
                    try {
                        const res = await fetch(`{{ route('chat.messages') }}?conversation_id=${this.conversationId}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
                            },
                        });
                        if (res.ok) {
                            const serverMessages = await res.json();
                            this.messages = serverMessages.map(m => ({
                                role: m.role,
                                content: m.content,
                            }));
                        }
                    } catch (e) {
                        // Ignore fetch errors for fallback reload
                    }
                }

                this.streaming = false;
                this.$nextTick(() => {
                    this.$refs.chatInput?.focus();
                    this.scrollToBottom();
                });
            }
        },

        copyTranscript() {
            const transcript = this.messages.map(msg => {
                const label = msg.role === 'user' ? 'User' : 'Assistant';
                return `${label}:\n${msg.content}`;
            }).join('\n\n---\n\n');

            navigator.clipboard.writeText(transcript).then(() => {
                this.transcriptCopied = true;
                setTimeout(() => this.transcriptCopied = false, 1500);
            });
        },

        resizeTextarea() {
            const textarea = this.$refs.chatInput;
            if (! textarea) return;
            textarea.style.height = 'auto';
            const maxHeight = parseInt(getComputedStyle(textarea).lineHeight) * 6;
            textarea.style.height = Math.min(textarea.scrollHeight, maxHeight) + 'px';
        },

        newChat() {
            $wire.call('newConversation');
            this.messages = [];
            this.streamedContent = '';
        }
    }"
    x-init="if (open) $nextTick(() => scrollToBottom())"
    @keydown.meta.k.window="toggle()"
    @toggle-chat-panel.window="toggle()"
    x-show="open"
    x-cloak
    class="sticky top-0 flex h-screen w-full max-w-md shrink-0 flex-col border-l border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
    @keydown.escape.window="if (open) close()"
>
    {{-- Header --}}
    <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
        <flux:dropdown position="bottom" align="start" class="min-w-0 flex-1">
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-down" class="max-w-full">
                <span class="block min-w-0 truncate text-left" x-text="conversationId ? ($wire.conversations.find(c => c.id === conversationId)?.title || '{{ __('Assistant') }}') : '{{ __('New conversation') }}'"></span>
            </flux:button>

            <flux:menu>
                @foreach ($conversations as $conversation)
                    <flux:menu.item
                        wire:click="switchConversation('{{ $conversation['id'] }}')"
                        class="max-w-64 truncate"
                    >
                        {{ $conversation['title'] }}
                    </flux:menu.item>
                @endforeach

                @if (empty($conversations))
                    <flux:menu.item disabled>
                        {{ __('No conversations yet') }}
                    </flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>

        <div class="flex items-center gap-2">
            <flux:button size="xs" variant="ghost" @click="copyTranscript()" x-show="messages.length > 0" x-bind:title="transcriptCopied ? 'Copied!' : 'Copy transcript'">
                <flux:icon.clipboard-document-list class="size-4" x-show="!transcriptCopied" />
                <flux:icon.check class="size-4 text-green-500" x-show="transcriptCopied" x-cloak />
            </flux:button>
            <flux:button size="xs" variant="ghost" @click="newChat()" title="New conversation">
                <flux:icon.plus class="size-4" />
            </flux:button>
            <flux:button size="xs" variant="ghost" @click="close()">
                <flux:icon.x-mark class="size-4" />
            </flux:button>
        </div>
    </div>

    {{-- Messages --}}
    <div x-ref="messageList" class="flex-1 space-y-4 overflow-y-auto p-4">
        <template x-if="messages.length === 0 && !streaming">
            <div class="flex h-full items-center justify-center">
                <flux:text class="text-center text-zinc-400">
                    {{ __('Ask me anything about your repos, agents, or work items.') }}
                </flux:text>
            </div>
        </template>

        <template x-for="(msg, index) in messages" :key="index">
            <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'" class="group relative">
                <div
                    :class="msg.role === 'user'
                        ? 'max-w-[80%] rounded-2xl rounded-br-md bg-zinc-800 px-4 py-2 text-sm text-white dark:bg-zinc-200 dark:text-zinc-900'
                        : 'chat-markdown max-w-[80%] rounded-2xl rounded-bl-md bg-zinc-100 px-4 py-2 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'"
                    x-html="msg.role === 'user' ? msg.content : window.renderMarkdown(msg.content)"
                ></div>
                <button
                    class="absolute -bottom-2 opacity-0 transition-opacity group-hover:opacity-100"
                    :class="msg.role === 'user' ? 'right-0' : 'left-0'"
                    x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText(msg.content).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                    :title="copied ? '{{ __('Copied!') }}' : '{{ __('Copy message') }}'"
                >
                    <template x-if="!copied">
                        <flux:icon.clipboard class="size-3.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300" />
                    </template>
                    <template x-if="copied">
                        <flux:icon.check class="size-3.5 text-green-500" />
                    </template>
                </button>
            </div>
        </template>

        {{-- Streaming indicator --}}
        <template x-if="streaming && streamedContent">
            <div class="flex justify-start">
                <div class="chat-markdown max-w-[80%] rounded-2xl rounded-bl-md bg-zinc-100 px-4 py-2 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100"
                     x-html="window.renderMarkdown(streamedContent)"></div>
            </div>
        </template>

        <template x-if="streaming && !streamedContent">
            <div class="flex justify-start">
                <div class="max-w-[80%] rounded-2xl rounded-bl-md bg-zinc-100 px-4 py-2 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">
                    <div class="flex items-center gap-1">
                        <div class="h-2 w-2 animate-bounce rounded-full bg-zinc-400" style="animation-delay: 0ms"></div>
                        <div class="h-2 w-2 animate-bounce rounded-full bg-zinc-400" style="animation-delay: 150ms"></div>
                        <div class="h-2 w-2 animate-bounce rounded-full bg-zinc-400" style="animation-delay: 300ms"></div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Input --}}
    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
        <form @submit.prevent="sendMessage()" class="flex items-end gap-2">
            <textarea
                x-ref="chatInput"
                x-model="currentMessage"
                placeholder="{{ __('Type a message...') }}"
                x-bind:disabled="streaming"
                @keydown.enter.prevent="if (!$event.shiftKey) sendMessage(); else { currentMessage += '\n'; $nextTick(() => resizeTextarea()); }"
                @input="resizeTextarea()"
                rows="1"
                class="flex-1 resize-none rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-zinc-400 focus:ring-1 focus:ring-zinc-400 disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-zinc-500 dark:focus:ring-zinc-500"
            ></textarea>
            <button
                type="submit"
                x-bind:disabled="!currentMessage.trim() || streaming"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 font-medium text-white transition hover:bg-zinc-800 disabled:opacity-50 disabled:hover:bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200 dark:disabled:opacity-50 dark:disabled:hover:bg-zinc-100"
            >
                <flux:icon.paper-airplane class="size-4" x-show="!streaming" />
                <flux:icon.arrow-path class="size-4 animate-spin" x-show="streaming" x-cloak />
            </button>
        </form>
        <div class="mt-2 flex items-center justify-between">
            <select
                x-model="selectedModel"
                @change="localStorage.setItem('chat-panel-model', $event.target.value)"
                class="rounded border border-zinc-200 bg-white px-2 py-0.5 text-xs text-zinc-500 outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-400"
            >
                <option value="">{{ __('Default model') }}</option>
                <optgroup label="{{ __('Strategy') }}">
                    <option value="cheapest">{{ __('Cheapest') }}</option>
                    <option value="smartest">{{ __('Smartest') }}</option>
                </optgroup>
                <optgroup label="{{ __('Anthropic') }}">
                    <option value="anthropic:claude-opus-4-6">Claude Opus 4.6</option>
                    <option value="anthropic:claude-sonnet-4-6">Claude Sonnet 4.6</option>
                    <option value="anthropic:claude-haiku-4-5-20251001">Claude Haiku 4.5</option>
                </optgroup>
                <optgroup label="{{ __('OpenAI') }}">
                    <option value="openai:gpt-4.1">GPT-4.1</option>
                    <option value="openai:gpt-4.1-mini">GPT-4.1 Mini</option>
                    <option value="openai:o3">o3</option>
                    <option value="openai:o4-mini">o4-mini</option>
                </optgroup>
                <optgroup label="{{ __('Gemini') }}">
                    <option value="gemini:gemini-2.5-pro">Gemini 2.5 Pro</option>
                    <option value="gemini:gemini-2.5-flash">Gemini 2.5 Flash</option>
                    <option value="gemini:gemini-2.0-flash">Gemini 2.0 Flash</option>
                </optgroup>
            </select>
            <flux:text size="xs" class="text-zinc-400">
                {{ __('Cmd+K to toggle') }}
            </flux:text>
        </div>
    </div>
</div>
