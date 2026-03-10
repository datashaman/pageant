<?php

use App\Jobs\ExecutePlan;
use App\Jobs\GeneratePlan;
use App\Models\Plan;
use App\Models\WorkItem;
use App\Services\WorkItemOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Work Item')] class extends Component {
    public WorkItem $workItem;

    public ?string $conversationId = null;

    public array $messages = [];

    /** @return array<string, bool> */
    #[Computed]
    public function availableProviders(): array
    {
        $user = auth()->user();
        $userKeyProviders = $user->apiKeys()->valid()->pluck('provider')->toArray();

        $providers = [];
        foreach (['anthropic', 'openai', 'gemini'] as $provider) {
            $providers[$provider] = ! empty(config("ai.providers.{$provider}.key"))
                || in_array($provider, $userKeyProviders);
        }

        return $providers;
    }

    public function mount(WorkItem $workItem): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($workItem->organization_id), 403);

        $this->workItem = $workItem->load(['organization', 'project']);

        if ($this->workItem->conversation_id) {
            $this->conversationId = $this->workItem->conversation_id;
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

    #[Computed]
    public function plans(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->workItem->plans()
            ->with('steps.agent', 'creator', 'approver')
            ->latest()
            ->get();
    }

    public function approvePlan(string $planId): void
    {
        $plan = Plan::where('organization_id', $this->workItem->organization_id)
            ->findOrFail($planId);

        if (! $plan->isPending()) {
            return;
        }

        $plan->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ExecutePlan::dispatch($plan);

        unset($this->plans);
    }

    public function cancelPlan(string $planId): void
    {
        $plan = Plan::where('organization_id', $this->workItem->organization_id)
            ->findOrFail($planId);

        app(WorkItemOrchestrator::class)->cancel($plan);

        unset($this->plans);
    }

    public function pausePlan(string $planId): void
    {
        $plan = Plan::where('organization_id', $this->workItem->organization_id)
            ->findOrFail($planId);

        if ($plan->isRunning()) {
            $plan->update(['status' => 'paused']);
        }

        unset($this->plans);
    }

    public function resumePlan(string $planId): void
    {
        $plan = Plan::where('organization_id', $this->workItem->organization_id)
            ->findOrFail($planId);

        if ($plan->isResumable()) {
            app(WorkItemOrchestrator::class)->prepareForResume($plan);
            ExecutePlan::dispatch($plan);
        }

        unset($this->plans);
    }

    public function confirmClose(): void
    {
        $this->dispatch('open-modal', id: 'confirm-close');
    }

    public function close(): void
    {
        $this->workItem->update(['status' => 'closed']);
        $this->workItem->refresh();

        $this->dispatch('close-modal', id: 'confirm-close');
    }

    public function reopen(): void
    {
        $this->workItem->update(['status' => 'open']);
        $this->workItem->refresh();
    }

    public function generatePlan(): void
    {
        $repoFullName = $this->workItem->source === 'github' && $this->workItem->source_reference
            ? Str::before($this->workItem->source_reference, '#')
            : '';

        if ($repoFullName) {
            GeneratePlan::dispatch($this->workItem, $repoFullName);
        }

        unset($this->plans);
    }
}; ?>

<div
    class="flex h-[calc(100vh-4rem)] flex-col"
    data-chat-context="{{ json_encode(['page' => 'work-items.show', 'work_item_id' => $workItem->id, 'work_item_title' => $workItem->title, 'work_item_description' => Str::limit($workItem->description, 200), 'project' => $workItem->project?->name, 'source' => $workItem->source, 'source_reference' => $workItem->source_reference]) }}"
    x-data="{
        currentMessage: '',
        selectedModel: localStorage.getItem('chat-panel-model') || '',
        streaming: false,
        streamedContent: '',
        messages: @entangle('messages'),
        conversationId: @entangle('conversationId'),

        scrollToBottom() {
            const container = this.$refs.messageArea;
            if (container) {
                this.$nextTick(() => { container.scrollTop = container.scrollHeight; });
            }
        },

        getPageContext() {
            const el = document.querySelector('[data-chat-context]');

            if (el) {
                try {
                    return el.dataset.chatContext;
                } catch (e) {
                    // Fall through
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

        resizeTextarea() {
            const textarea = this.$refs.chatInput;
            if (! textarea) return;
            textarea.style.height = 'auto';
            const maxHeight = parseInt(getComputedStyle(textarea).lineHeight) * 6;
            textarea.style.height = Math.min(textarea.scrollHeight, maxHeight) + 'px';
        },
    }"
    x-init="$nextTick(() => scrollToBottom())"
    data-hide-chat-panel
>
    {{-- Scrollable content area --}}
    <div x-ref="messageArea" class="min-h-0 flex-1 space-y-6 overflow-y-auto">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('work-items.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $workItem->title }}</flux:heading>
                <flux:badge :variant="$workItem->isOpen() ? 'success' : 'default'" size="sm">
                    {{ ucfirst($workItem->status) }}
                </flux:badge>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('work-items.edit', $workItem) }}" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                @if ($workItem->isOpen())
                    <flux:button variant="outline" wire:click="confirmClose" wire:target="confirmClose">
                        {{ __('Close') }}
                    </flux:button>
                @else
                    <flux:button wire:click="reopen" wire:target="reopen">
                        {{ __('Reopen') }}
                    </flux:button>
                @endif
            </div>
        </div>

        @if ($workItem->description)
            <div>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:text>{{ $workItem->description }}</flux:text>
            </div>
        @endif

        <div class="max-w-xl grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <flux:label>{{ __('Organization') }}</flux:label>
                <flux:text>{{ $workItem->organization->name }}</flux:text>
            </div>

            @if ($workItem->project)
                <div>
                    <flux:label>{{ __('Project') }}</flux:label>
                    <flux:link href="{{ route('projects.show', $workItem->project) }}" wire:navigate>
                        {{ $workItem->project->name }}
                    </flux:link>
                </div>
            @endif

            @if ($workItem->board_id)
                <div>
                    <flux:label>{{ __('Board ID') }}</flux:label>
                    <flux:text>{{ $workItem->board_id }}</flux:text>
                </div>
            @endif

            @if ($workItem->source_reference || $workItem->source_url)
                <div>
                    <flux:label>{{ __('Source') }}</flux:label>
                    <x-source-link
                        :source="$workItem->source"
                        :source-reference="$workItem->source_reference"
                        :source-url="$workItem->source_url"
                    />
                </div>
            @endif

            <div>
                <flux:label>{{ __('Created') }}</flux:label>
                <flux:text>{{ $workItem->created_at->format('M j, Y g:i A') }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Updated') }}</flux:label>
                <flux:text>{{ $workItem->updated_at->format('M j, Y g:i A') }}</flux:text>
            </div>
        </div>

        {{-- Chat messages (in the scrollable area, above plans) --}}
        <template x-if="messages.length > 0 || streaming">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Conversation') }}</flux:heading>
                <div class="space-y-3">
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
                                    <div class="h-2 w-2 animate-bounce animate-bounce-delay-0 rounded-full bg-zinc-400"></div>
                                    <div class="h-2 w-2 animate-bounce animate-bounce-delay-150 rounded-full bg-zinc-400"></div>
                                    <div class="h-2 w-2 animate-bounce animate-bounce-delay-300 rounded-full bg-zinc-400"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Plans Section --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Plans') }}</flux:heading>
                @if ($workItem->isOpen() && $workItem->source === 'github' && $workItem->source_reference && ! $this->plans->contains(fn ($p) => $p->isPending() || $p->isApproved() || $p->isRunning() || $p->isPaused()))
                    <flux:button size="sm" variant="primary" wire:click="generatePlan" wire:target="generatePlan" class="inline-flex items-center gap-2">
                        <flux:icon.bolt class="size-4 shrink-0" wire:loading.remove wire:target="generatePlan" />
                        <flux:icon.arrow-path class="size-4 shrink-0 animate-spin" wire:loading wire:target="generatePlan" />
                        <span>{{ __('Generate Plan') }}</span>
                    </flux:button>
                @endif
            </div>

            @forelse ($this->plans as $plan)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-4" wire:key="plan-{{ $plan->id }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:heading size="base">{{ __('Plan') }}</flux:heading>
                            @php
                                $badgeVariant = match($plan->status) {
                                    'pending' => 'warning',
                                    'approved' => 'info',
                                    'running' => 'info',
                                    'paused' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'cancelled' => 'default',
                                    default => 'default',
                                };
                            @endphp
                            <flux:badge :variant="$badgeVariant" size="sm">
                                {{ ucfirst($plan->status) }}
                            </flux:badge>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($plan->isPending())
                                <flux:button size="sm" variant="primary" wire:click="approvePlan('{{ $plan->id }}')">
                                    {{ __('Approve') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelPlan('{{ $plan->id }}')">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @elseif ($plan->isRunning())
                                <flux:button size="sm" wire:click="pausePlan('{{ $plan->id }}')">
                                    {{ __('Pause') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelPlan('{{ $plan->id }}')">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @elseif ($plan->isPaused())
                                <flux:button size="sm" variant="primary" wire:click="resumePlan('{{ $plan->id }}')">
                                    {{ __('Resume') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelPlan('{{ $plan->id }}')">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @elseif ($plan->isFailed())
                                <flux:button size="sm" variant="primary" wire:click="resumePlan('{{ $plan->id }}')">
                                    {{ __('Resume') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    @if ($plan->summary)
                        <flux:text class="text-sm">{{ $plan->summary }}</flux:text>
                    @endif

                    <div class="text-xs text-zinc-500 dark:text-zinc-400 space-x-4">
                        <span>{{ __('Created :time', ['time' => $plan->created_at->diffForHumans()]) }}</span>
                        @if ($plan->approved_at)
                            <span>{{ __('Approved :time', ['time' => $plan->approved_at->diffForHumans()]) }}</span>
                        @endif
                        @if ($plan->completed_at)
                            <span>{{ __('Finished :time', ['time' => $plan->completed_at->diffForHumans()]) }}</span>
                        @endif
                    </div>

                    {{-- Steps --}}
                    @if ($plan->steps->isNotEmpty())
                        <div class="space-y-2">
                            @foreach ($plan->steps as $step)
                                <div class="flex items-start gap-3 rounded-md px-3 py-2 {{ $step->isRunning() ? 'bg-blue-50 dark:bg-blue-950/30' : ($step->isFailed() ? 'bg-red-50 dark:bg-red-950/30' : 'bg-zinc-50 dark:bg-zinc-800/50') }}" wire:key="step-{{ $step->id }}">
                                    <div class="mt-0.5 shrink-0">
                                        @if ($step->isCompleted())
                                            <div class="size-5 rounded-full bg-green-500 flex items-center justify-center">
                                                <svg class="size-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            </div>
                                        @elseif ($step->isFailed())
                                            <div class="size-5 rounded-full bg-red-500 flex items-center justify-center">
                                                <svg class="size-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                            </div>
                                        @elseif ($step->isRunning())
                                            <div class="size-5 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
                                        @elseif ($step->status === 'skipped')
                                            <div class="size-5 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                                        @else
                                            <div class="size-5 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium">{{ $step->order }}.</span>
                                            <span class="text-sm">{{ $step->description }}</span>
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                            {{ __('Agent: :name', ['name' => $step->agent->name]) }}
                                        </div>
                                        @if ($step->result)
                                            <div class="text-xs text-zinc-600 dark:text-zinc-300 mt-1 italic">
                                                {{ Str::limit($step->result, 200) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <flux:text class="text-zinc-500">{{ __('No plans yet. Plans are created when agents analyze this work item.') }}</flux:text>
            @endforelse
        </div>

        <flux:modal name="confirm-close">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Close Work Item') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to close ":title"?', ['title' => $workItem->title]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="close" wire:target="close">{{ __('Close') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>

    {{-- Sticky bottom chat console --}}
    <div class="shrink-0 border-t border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <form @submit.prevent="sendMessage()" class="flex items-end gap-3">
            <div class="min-w-0 flex-1">
                <textarea
                    x-ref="chatInput"
                    x-model="currentMessage"
                    placeholder="{{ __('Ask to make changes...') }}"
                    x-bind:disabled="streaming"
                    @keydown.enter.prevent="if (!$event.shiftKey) sendMessage(); else { currentMessage += '\n'; $nextTick(() => resizeTextarea()); }"
                    @input="resizeTextarea()"
                    rows="1"
                    class="w-full resize-none rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-zinc-400 focus:ring-1 focus:ring-zinc-400 disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-zinc-500 dark:focus:ring-zinc-500"
                ></textarea>
            </div>
            <button
                type="submit"
                x-bind:disabled="!currentMessage.trim() || streaming"
                class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-900 font-medium text-white transition hover:bg-zinc-800 disabled:opacity-50 disabled:hover:bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200 dark:disabled:opacity-50 dark:disabled:hover:bg-zinc-100"
            >
                <flux:icon.paper-airplane class="size-4" x-show="!streaming" />
                <flux:icon.arrow-path class="size-4 animate-spin" x-show="streaming" x-cloak />
            </button>
        </form>
        <div class="mt-2 flex items-center gap-3">
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
                    <option value="anthropic:claude-opus-4-6" @disabled(! $this->availableProviders['anthropic'])>Claude Opus 4.6</option>
                    <option value="anthropic:claude-sonnet-4-6" @disabled(! $this->availableProviders['anthropic'])>Claude Sonnet 4.6</option>
                    <option value="anthropic:claude-haiku-4-5-20251001" @disabled(! $this->availableProviders['anthropic'])>Claude Haiku 4.5</option>
                </optgroup>
                <optgroup label="{{ __('OpenAI') }}">
                    <option value="openai:gpt-4.1" @disabled(! $this->availableProviders['openai'])>GPT-4.1</option>
                    <option value="openai:gpt-4.1-mini" @disabled(! $this->availableProviders['openai'])>GPT-4.1 Mini</option>
                    <option value="openai:o3" @disabled(! $this->availableProviders['openai'])>o3</option>
                    <option value="openai:o4-mini" @disabled(! $this->availableProviders['openai'])>o4-mini</option>
                </optgroup>
                <optgroup label="{{ __('Gemini') }}">
                    <option value="gemini:gemini-2.5-pro" @disabled(! $this->availableProviders['gemini'])>Gemini 2.5 Pro</option>
                    <option value="gemini:gemini-2.5-flash" @disabled(! $this->availableProviders['gemini'])>Gemini 2.5 Flash</option>
                    <option value="gemini:gemini-2.0-flash" @disabled(! $this->availableProviders['gemini'])>Gemini 2.0 Flash</option>
                </optgroup>
            </select>
            <span class="text-xs text-zinc-400" x-show="streaming">
                <flux:icon.arrow-path class="inline size-3 animate-spin" /> {{ __('Thinking...') }}
            </span>
        </div>
    </div>

    <livewire:changes-files-panel :work-item="$workItem" />
</div>
