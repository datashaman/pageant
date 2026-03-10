<?php

use App\Models\Workspace;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('View Workspace')] class extends Component {
    public Workspace $workspace;

    public function mount(Workspace $workspace): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($workspace->organization_id), 403);

        $this->workspace = $workspace->load(['organization', 'references', 'agents', 'skills']);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete');
    }

    public function delete(): void
    {
        $this->workspace->delete();

        $this->redirect(route('workspaces.index'), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'workspaces.show', 'workspace_id' => $workspace->id, 'workspace_name' => $workspace->name]) }}">
    <div class="space-y-6">
        <x-show-header
            :back-url="route('workspaces.index')"
            :title="$workspace->name"
            :edit-url="route('workspaces.edit', $workspace)"
        />

        <div class="max-w-xl space-y-6">
            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Organization') }}</flux:heading>
                <flux:text>{{ $workspace->organization->name }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</flux:heading>
                <flux:text>{{ $workspace->description ?: __('None') }}</flux:text>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('References') }}</flux:heading>
                @if ($workspace->references->isNotEmpty())
                    <div class="mt-1 space-y-2">
                        @foreach ($workspace->references as $reference)
                            <div class="flex items-center gap-2">
                                <flux:badge>{{ $reference->source }}</flux:badge>
                                @if ($reference->source_url)
                                    <flux:link href="{{ $reference->source_url }}" target="_blank">
                                        {{ $reference->source_reference }}
                                    </flux:link>
                                @else
                                    <flux:text>{{ $reference->source_reference }}</flux:text>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('None') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Agents') }}</flux:heading>
                @if ($workspace->agents->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($workspace->agents as $agent)
                            <flux:badge>{{ $agent->name }}</flux:badge>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('None') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Skills') }}</flux:heading>
                @if ($workspace->skills->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($workspace->skills as $skill)
                            <flux:badge>{{ $skill->name }}</flux:badge>
                        @endforeach
                    </div>
                @else
                    <flux:text>{{ __('None') }}</flux:text>
                @endif
            </div>
        </div>

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Workspace') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $workspace->name]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
