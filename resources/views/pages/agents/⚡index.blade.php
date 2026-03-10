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

    /** @var list<string> */
    private const SORTABLE_FIELDS = ['name', 'provider', 'model'];

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
    public function agents(): LengthAwarePaginator
    {
        return Agent::query()
            ->forCurrentOrganization()
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
        $agent = Agent::query()->forCurrentOrganization()->findOrFail($id);
        $agent->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'agents.index']) }}">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Agents') }}</flux:heading>
            <flux:button variant="primary" href="{{ route('agents.create') }}" wire:navigate>
                {{ __('Create Agent') }}
            </flux:button>
        </div>

        @if ($this->agents->isEmpty() && ! $this->search)
            <x-empty-state :heading="__('No agents yet')" :description="__('Agents are AI-powered workers that can respond to events, review code, and complete tasks on your repos automatically.')">
                <x-slot:icon>
                    <flux:icon.cpu-chip class="size-10 text-zinc-400 dark:text-zinc-500" />
                </x-slot:icon>
            </x-empty-state>
        @else
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
                            <flux:table.cell>{{ $agent->model_display_name }}</flux:table.cell>
                            <flux:table.cell>{{ $agent->organization->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" href="{{ route('agents.edit', $agent) }}" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="outline" wire:click="confirmDelete('{{ $agent->id }}')">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>

                                <x-confirm-delete-modal
                                    :id="'confirm-delete-' . $agent->id"
                                    :title="__('Delete Agent')"
                                    :item-name="$agent->name"
                                    :delete-id="$agent->id"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center">
                                {{ __('No agents match your search.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
