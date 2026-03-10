<?php

use App\Models\Workspace;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Workspaces')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** @var list<string> */
    private const SORTABLE_FIELDS = ['name', 'created_at'];

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE_FIELDS, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function workspaces(): LengthAwarePaginator
    {
        return Workspace::query()
            ->forCurrentOrganization()
            ->with('organization')
            ->withCount('references')
            ->when($this->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    public function confirmDelete(string $id): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete-' . $id);
    }

    public function delete(string $id): void
    {
        $workspace = Workspace::query()->forCurrentOrganization()->findOrFail($id);
        $workspace->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'workspaces.index']) }}">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Workspaces') }}</flux:heading>
            <flux:button variant="outline" href="{{ route('workspaces.create') }}" wire:navigate class="!border-zinc-300 !text-zinc-900 hover:!bg-zinc-100 dark:!border-zinc-600 dark:!text-zinc-100 dark:hover:!bg-zinc-800">
                {{ __('Create Workspace') }}
            </flux:button>
        </div>

        @if ($this->workspaces->isEmpty() && ! $this->search)
            <x-empty-state :heading="__('No workspaces yet')" :description="__('Workspaces group repos and issues into focused work streams with shared conversations, agents, and skills.')">
                <x-slot:icon>
                    <flux:icon.rectangle-group class="size-10 text-zinc-400 dark:text-zinc-500" />
                </x-slot:icon>
            </x-empty-state>
        @else
            <flux:input wire:model.live="search" placeholder="{{ __('Search workspaces...') }}" icon="magnifying-glass" />

            <flux:table :paginate="$this->workspaces">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">
                        {{ __('Name') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Description') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('References') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Organization') }}
                    </flux:table.column>
                    <flux:table.column align="end">
                        {{ __('Actions') }}
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->workspaces as $workspace)
                        <flux:table.row :key="$workspace->id">
                            <flux:table.cell>
                                <flux:link href="{{ route('workspaces.show', $workspace) }}" wire:navigate>
                                    {{ $workspace->name }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ Str::limit($workspace->description, 60) }}</flux:table.cell>
                            <flux:table.cell>{{ $workspace->references_count }}</flux:table.cell>
                            <flux:table.cell>{{ $workspace->organization->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" href="{{ route('workspaces.edit', $workspace) }}" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="confirmDelete('{{ $workspace->id }}')">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>

                                <x-confirm-delete-modal
                                    :id="'confirm-delete-' . $workspace->id"
                                    :title="__('Delete Workspace')"
                                    :item-name="$workspace->name"
                                    :delete-id="$workspace->id"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center">
                                {{ __('No workspaces match your search.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
