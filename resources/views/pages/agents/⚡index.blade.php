<?php

use App\Models\Agent;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Agents')] class extends Component {
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
    public function agents(): LengthAwarePaginator
    {
        return Agent::query()
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
        $agent = Agent::query()->forUser()->findOrFail($id);
        $agent->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Agents') }}</flux:heading>
            <flux:button variant="primary" href="{{ route('agents.create') }}" wire:navigate>
                {{ __('Create Agent') }}
            </flux:button>
        </div>

        <flux:input wire:model.live="search" placeholder="{{ __('Search agents...') }}" icon="magnifying-glass" />

        <flux:table :paginate="$this->agents">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">
                    {{ __('Name') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'provider'" :direction="$sortDirection" wire:click="sortBy('provider')">
                    {{ __('Provider') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'model'" :direction="$sortDirection" wire:click="sortBy('model')">
                    {{ __('Model') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Organization') }}
                </flux:table.column>
                <flux:table.column align="end">
                    {{ __('Actions') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->agents as $agent)
                    <flux:table.row :key="$agent->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('agents.show', $agent) }}" wire:navigate>
                                {{ $agent->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $agent->provider }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->model }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->organization->title }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" href="{{ route('agents.edit', $agent) }}" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmDelete('{{ $agent->id }}')">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>

                            <flux:modal name="confirm-delete-{{ $agent->id }}">
                                <div class="space-y-6">
                                    <flux:heading size="lg">{{ __('Delete Agent') }}</flux:heading>
                                    <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $agent->name]) }}</flux:text>
                                    <div class="flex justify-end gap-3">
                                        <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="danger" wire:click="delete('{{ $agent->id }}')">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">
                            {{ __('No agents found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
