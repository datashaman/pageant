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

    /** @var list<string> */
    private const SORTABLE_FIELDS = ['name', 'provider'];

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

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'skills.index']) }}">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Skills') }}</flux:heading>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('skills.registry') }}" wire:navigate>
                    {{ __('Browse Registry') }}
                </flux:button>
                <flux:button variant="primary" href="{{ route('skills.create') }}" wire:navigate>
                    {{ __('Create Skill') }}
                </flux:button>
            </div>
        </div>

        @if ($this->skills->isEmpty() && ! $this->search)
            <x-empty-state :heading="__('No skills yet')" :description="__('Skills define reusable capabilities that agents can use when working on tasks, such as code review, testing, or deployment.')">
                <x-slot:icon>
                    <flux:icon.bolt class="size-10 text-zinc-400 dark:text-zinc-500" />
                </x-slot:icon>
                <x-slot:action>
                    <flux:button variant="primary" href="{{ route('skills.create') }}" wire:navigate>
                        {{ __('Create Skill') }}
                    </flux:button>
                </x-slot:action>
            </x-empty-state>
        @else
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
                                    <flux:button size="sm" variant="outline" wire:click="confirmDelete('{{ $skill->id }}')">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>

                                <x-confirm-delete-modal
                                    :id="'confirm-delete-' . $skill->id"
                                    :title="__('Delete Skill')"
                                    :item-name="$skill->name"
                                    :delete-id="$skill->id"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center">
                                {{ __('No skills match your search.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
