<?php

use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Organizations')] class extends Component {
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
    public function organizations(): LengthAwarePaginator
    {
        return auth()->user()->organizations()
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
        $organization = auth()->user()->organizations()->findOrFail($id);
        $organization->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Organizations') }}</flux:heading>
            <flux:button variant="primary" href="{{ route('organizations.create') }}" wire:navigate>
                {{ __('Create Organization') }}
            </flux:button>
        </div>

        <flux:input wire:model.live="search" placeholder="{{ __('Search organizations...') }}" icon="magnifying-glass" />

        <flux:table :paginate="$this->organizations">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'title'" :direction="$sortDirection" wire:click="sortBy('title')">
                    {{ __('Title') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'slug'" :direction="$sortDirection" wire:click="sortBy('slug')">
                    {{ __('Slug') }}
                </flux:table.column>
                <flux:table.column align="end">
                    {{ __('Actions') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->organizations as $organization)
                    <flux:table.row :key="$organization->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('organizations.show', $organization) }}" wire:navigate>
                                {{ $organization->title }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $organization->slug }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" href="{{ route('organizations.edit', $organization) }}" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmDelete('{{ $organization->id }}')">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>

                            <flux:modal name="confirm-delete-{{ $organization->id }}">
                                <div class="space-y-6">
                                    <flux:heading size="lg">{{ __('Delete Organization') }}</flux:heading>
                                    <flux:text>{{ __('Are you sure you want to delete ":title"? This action cannot be undone.', ['title' => $organization->title]) }}</flux:text>
                                    <div class="flex justify-end gap-3">
                                        <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="danger" wire:click="delete('{{ $organization->id }}')">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center">
                            {{ __('No organizations found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
