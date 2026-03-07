<?php

use App\Models\Skill;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Skills')] class extends Component {
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
    public function skills(): LengthAwarePaginator
    {
        return Skill::query()
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
        $skill = Skill::query()->forCurrentOrganization()->findOrFail($id);
        $skill->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Skills') }}</flux:heading>
            <flux:button variant="primary" href="{{ route('skills.create') }}" wire:navigate>
                {{ __('Create Skill') }}
            </flux:button>
        </div>

        <flux:input wire:model.live="search" placeholder="{{ __('Search skills...') }}" icon="magnifying-glass" />

        <flux:table :paginate="$this->skills">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">
                    {{ __('Name') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'provider'" :direction="$sortDirection" wire:click="sortBy('provider')">
                    {{ __('Provider') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Enabled') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Organization') }}
                </flux:table.column>
                <flux:table.column align="end">
                    {{ __('Actions') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->skills as $skill)
                    <flux:table.row :key="$skill->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('skills.show', $skill) }}" wire:navigate>
                                {{ $skill->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $skill->provider }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :variant="$skill->enabled ? 'primary' : 'outline'" size="sm">
                                {{ $skill->enabled ? __('Yes') : __('No') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $skill->organization->name }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" href="{{ route('skills.edit', $skill) }}" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmDelete('{{ $skill->id }}')">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>

                            <flux:modal name="confirm-delete-{{ $skill->id }}">
                                <div class="space-y-6">
                                    <flux:heading size="lg">{{ __('Delete Skill') }}</flux:heading>
                                    <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $skill->name]) }}</flux:text>
                                    <div class="flex justify-end gap-3">
                                        <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="danger" wire:click="delete('{{ $skill->id }}')">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">
                            {{ __('No skills found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
