<?php

use App\Models\WorkItem;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Work Item')] class extends Component {
    public WorkItem $workItem;

    public function mount(WorkItem $workItem): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($workItem->organization_id), 403);

        $this->workItem = $workItem->load(['organization', 'project']);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete');
    }

    public function delete(): void
    {
        $this->workItem->delete();

        $this->redirect(route('work-items.index'), navigate: true);
    }
}; ?>

<div>title">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('work-items.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $workItem->title }}</flux:heading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('work-items.edit', $workItem) }}" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button variant="danger" wire:click="confirmDelete">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>

        <div class="max-w-xl space-y-4">
            <div>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:text>{{ $workItem->title }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:text>{{ $workItem->description ?: '—' }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Organization') }}</flux:label>
                <flux:text>{{ $workItem->organization->title }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Project') }}</flux:label>
                @if ($workItem->project)
                    <flux:link href="{{ route('projects.show', $workItem->project) }}" wire:navigate>
                        {{ $workItem->project->name }}
                    </flux:link>
                @else
                    <flux:text>{{ __('—') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:label>{{ __('Source') }}</flux:label>
                <flux:text>{{ $workItem->source }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Board ID') }}</flux:label>
                <flux:text>{{ $workItem->board_id ?: '—' }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Source Reference') }}</flux:label>
                <flux:text>{{ $workItem->source_reference ?: '—' }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Source URL') }}</flux:label>
                @if ($workItem->source_url)
                    <flux:link href="{{ $workItem->source_url }}" target="_blank">
                        {{ $workItem->source_url }}
                    </flux:link>
                @else
                    <flux:text>{{ __('—') }}</flux:text>
                @endif
            </div>

            <div>
                <flux:label>{{ __('Created') }}</flux:label>
                <flux:text>{{ $workItem->created_at->format('M j, Y g:i A') }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Updated') }}</flux:label>
                <flux:text>{{ $workItem->updated_at->format('M j, Y g:i A') }}</flux:text>
            </div>
        </div>

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Work Item') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":title"? This action cannot be undone.', ['title' => $workItem->title]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
