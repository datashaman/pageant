<?php

use App\Models\Agent;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('View Agent')] class extends Component {
    public Agent $agent;

    public function mount(Agent $agent): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($agent->organization_id), 403);

        $this->agent = $agent->load(['organization', 'skills', 'repos']);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete');
    }

    public function delete(): void
    {
        $this->agent->delete();

        $this->redirect(route('agents.index'), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'agents.show', 'agent_id' => $agent->id, 'agent_name' => $agent->name, 'agent_description' => Str::limit($agent->description, 200)]) }}">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('agents.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $agent->name }}</flux:heading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('agents.edit', $agent) }}" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button variant="danger" wire:click="confirmDelete">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>

        <div class="max-w-xl space-y-6">
            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Organization') }}</flux:heading>
                <flux:text>{{ $agent->organization->name }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</flux:heading>
                <flux:text>{{ $agent->description ?: __('None') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</flux:heading>
                <flux:text>{{ $agent->provider ?: __('None') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</flux:heading>
                <flux:text>{{ $agent->model ?: __('None') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Permission Mode') }}</flux:heading>
                <flux:text>{{ $agent->permission_mode ?: __('None') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Max Turns') }}</flux:heading>
                <flux:text>{{ $agent->max_turns ?? __('None') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Background') }}</flux:heading>
                <flux:text>{{ $agent->background ? __('Yes') : __('No') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Isolation') }}</flux:heading>
                <flux:text>{{ $agent->isolation ?: __('None') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Events') }}</flux:heading>
                @if (!empty($agent->events))
                    <div class="mt-1 space-y-2">
                        @foreach ($agent->events as $subscription)
                            @php
                                $entry = is_string($subscription) ? ['event' => $subscription, 'filters' => []] : $subscription;
                                $eventKey = $entry['event'];
                                $filters = $entry['filters'] ?? [];
                            @endphp
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge>{{ $eventKey }}</flux:badge>
                                @if (!empty($filters['labels']))
                                    <flux:badge color="blue">labels: {{ implode(', ', $filters['labels']) }}</flux:badge>
                                @endif
                                @if (!empty($filters['base_branch']))
                                    <flux:badge color="green">base: {{ $filters['base_branch'] }}</flux:badge>
                                @endif
                                @if (!empty($filters['branches']))
                                    <flux:badge color="purple">branches: {{ implode(', ', $filters['branches']) }}</flux:badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('None') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Tools') }}</flux:heading>
                @if (!empty($agent->tools))
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($agent->tools as $tool)
                            <flux:badge>{{ $tool }}</flux:badge>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('None') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Skills') }}</flux:heading>
                @if ($agent->skills->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($agent->skills as $skill)
                            <flux:badge>{{ $skill->name }}</flux:badge>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('None') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Repos') }}</flux:heading>
                @if ($agent->repos->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($agent->repos as $repo)
                            <flux:badge>{{ $repo->name }}</flux:badge>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('None') }}</flux:text>
                @endif
            </div>
        </div>

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Agent') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $agent->name]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
