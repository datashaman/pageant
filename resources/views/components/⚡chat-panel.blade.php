<?php

use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\ConversationStore;
use Livewire\Component;

new class extends Component
{
    public ?string $conversationId = null;

    public array $messages = [];

    public function mount(): void
    {
        $store = resolve(ConversationStore::class);
        $this->conversationId = $store->latestConversationId(auth()->id());

        if ($this->conversationId) {
            $this->loadMessages();
        }
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
        streaming: false,
        streamedContent: '',
        messages: @entangle('messages'),
        conversationId: @entangle('conversationId'),

        toggle() {
            this.open = ! this.open;
            localStorage.setItem('chat-panel-open', JSON.stringify(this.open));
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
            const path = window.location.pathname;
            const parts = path.split('/').filter(Boolean);

            if (parts.length === 0) return 'User is on the dashboard';

            const resource = parts[0];
            const action = parts.length > 1 ? parts[parts.length - 1] : 'index';

            if (action === 'create') return `User is on the ${resource} create page`;
            if (action === 'edit') return `User is editing a ${resource.replace(/s$/, '')}`;
            if (parts.length > 1 && action !== 'index') return `User is viewing a ${resource.replace(/s$/, '')}`;

            return `User is on the ${resource} index page`;
        },

        async sendMessage() {
            const message = this.currentMessage.trim();
            if (! message || this.streaming) return;

            this.currentMessage = '';
            this.messages.push({ role: 'user', content: message });
            this.streaming = true;
            this.streamedContent = '';

            this.$nextTick(() => this.scrollToBottom());

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
                    }),
                });

                if (! response.ok) {
                    const error = await response.json();
                    this.messages.push({ role: 'assistant', content: error.error || 'An error occurred.' });
                    this.streaming = false;
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();

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
                }

                if (this.streamedContent) {
                    this.messages.push({ role: 'assistant', content: this.streamedContent });
                    this.streamedContent = '';
                }
            } catch (error) {
                this.messages.push({ role: 'assistant', content: 'Failed to connect. Please try again.' });
            } finally {
                this.streaming = false;
            }
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
>
    {{-- Toggle Button (mobile only, desktop uses sidebar item) --}}
    <button
        @click="toggle()"
        class="fixed bottom-6 right-6 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-800 text-white shadow-lg transition hover:bg-zinc-700 dark:bg-zinc-200 dark:text-zinc-800 dark:hover:bg-zinc-300 lg:hidden"
    >
        <flux:icon.chat-bubble-left-right class="size-5" />
    </button>

    {{-- Slide-over Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
        @keydown.escape.window="open = false; localStorage.setItem('chat-panel-open', 'false')"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Assistant') }}</flux:heading>
            <div class="flex items-center gap-2">
                <flux:button size="xs" variant="ghost" @click="newChat()" title="New conversation">
                    <flux:icon.plus class="size-4" />
                </flux:button>
                <flux:button size="xs" variant="ghost" @click="open = false; localStorage.setItem('chat-panel-open', 'false')">
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
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div
                        :class="msg.role === 'user'
                            ? 'max-w-[80%] rounded-2xl rounded-br-md bg-zinc-800 px-4 py-2 text-sm text-white dark:bg-zinc-200 dark:text-zinc-900'
                            : 'chat-markdown max-w-[80%] rounded-2xl rounded-bl-md bg-zinc-100 px-4 py-2 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'"
                        x-html="msg.role === 'user' ? msg.content : window.renderMarkdown(msg.content)"
                    ></div>
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
            <form @submit.prevent="sendMessage()" class="flex gap-2">
                <flux:input
                    x-model="currentMessage"
                    placeholder="{{ __('Type a message...') }}"
                    x-bind:disabled="streaming"
                    @keydown.enter.prevent="if (!$event.shiftKey) sendMessage()"
                    class="flex-1"
                />
                <flux:button type="submit" variant="primary" size="sm" x-bind:disabled="!currentMessage.trim() || streaming">
                    <flux:icon.paper-airplane class="size-4" />
                </flux:button>
            </form>
            <flux:text size="xs" class="mt-2 text-center text-zinc-400">
                {{ __('Cmd+K to toggle') }}
            </flux:text>
        </div>
    </div>

    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-40 bg-black/25"
        @click="open = false; localStorage.setItem('chat-panel-open', 'false')"
    ></div>
</div>
