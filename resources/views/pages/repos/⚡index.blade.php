<?php

use App\Models\Repo;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Repos')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
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
    public function repos(): LengthAwarePaginator
    {
        return Repo::query()
            ->forUser()
            ->with('organization')
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
        $repo = Repo::query()->forUser()->findOrFail($id);
        $repo->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Repos') }}</flux:heading>
            <flux:button variant="primary" href="{{ route('repos.create') }}" wire:navigate>
                {{ __('Create Repo') }}
            </flux:button>
        </div>

        <flux:input wire:model.live="search" placeholder="{{ __('Search repos...') }}" icon="magnifying-glass" />

        <flux:table :paginate="$this->repos">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">
                    {{ __('Name') }}
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
                @forelse ($this->repos as $repo)
                    <flux:table.row :key="$repo->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('repos.show', $repo) }}" wire:navigate>
                                {{ $repo->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $repo->source }}</flux:table.cell>
                        <flux:table.cell>{{ $repo->organization->title }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" href="{{ route('repos.edit', $repo) }}" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmDelete('{{ $repo->id }}')">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>

                            <flux:modal name="confirm-delete-{{ $repo->id }}">
                                <div class="space-y-6">
                                    <flux:heading size="lg">{{ __('Delete Repo') }}</flux:heading>
                                    <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $repo->name]) }}</flux:text>
                                    <div class="flex justify-end gap-3">
                                        <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="danger" wire:click="delete('{{ $repo->id }}')">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">
                            {{ __('No repos found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
