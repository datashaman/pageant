<?php

use App\Models\WorkItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Work Items')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortField = 'title';
    public string $sortDirection = 'asc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function workItems(): LengthAwarePaginator
    {
        return WorkItem::query()
            ->forUser()
            ->with(['organization', 'project'])
            ->when($this->search, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    public function confirmDelete(string $id): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete-' . $id);
    }

    public function delete(string $id): void
    {
        $workItem = WorkItem::query()->forUser()->findOrFail($id);
        $workItem->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Work Items') }}</flux:heading>
            <flux:button variant="primary" href="{{ route('work-items.create') }}" wire:navigate>
                {{ __('New Work Item') }}
            </flux:button>
        </div>

        <flux:input wire:model.live="search" placeholder="{{ __('Search work items...') }}" icon="magnifying-glass" />

        <flux:table :paginate="$this->workItems">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'title'" :direction="$sortDirection" wire:click="sortBy('title')">
                    {{ __('Title') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Project') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'source'" :direction="$sortDirection" wire:click="sortBy('source')">
                    {{ __('Source') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Organization') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Actions') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->workItems as $workItem)
                    <flux:table.row :key="$workItem->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('work-items.show', $workItem) }}" wire:navigate>
                                {{ $workItem->title }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($workItem->project)
                                <flux:link href="{{ route('projects.show', $workItem->project) }}" wire:navigate>
                                    {{ $workItem->project->name }}
                                </flux:link>
                            @else
                                &mdash;
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $workItem->source }}</flux:table.cell>
                        <flux:table.cell>{{ $workItem->organization->title }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" href="{{ route('work-items.edit', $workItem) }}" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmDelete('{{ $workItem->id }}')">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>

                            <flux:modal name="confirm-delete-{{ $workItem->id }}">
                                <div class="space-y-6">
                                    <flux:heading size="lg">{{ __('Delete Work Item') }}</flux:heading>
                                    <flux:text>{{ __('Are you sure you want to delete ":title"? This action cannot be undone.', ['title' => $workItem->title]) }}</flux:text>
                                    <div class="flex justify-end gap-3">
                                        <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="danger" wire:click="delete('{{ $workItem->id }}')">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">
                            {{ __('No work items found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
