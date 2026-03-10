<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Projects')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    /** @var list<string> */
    private const SORTABLE_FIELDS = ['name'];

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE_FIELDS, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function projects()
    {
        return Project::query()
            ->forCurrentOrganization()
            ->with('organization')
            ->when($this->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    public function delete(Project $project): void
    {
        abort_unless(auth()->user()->organizations->contains('id', $project->organization_id), 403);

        $project->delete();
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'projects.index']) }}">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Projects') }}</flux:heading>

            <flux:button href="{{ route('projects.create') }}" wire:navigate variant="primary">
                {{ __('Create Project') }}
            </flux:button>
        </div>

        @if ($this->projects->isEmpty() && ! $this->search)
            <x-empty-state :heading="__('No projects yet')" :description="__('Projects group related work items, repos, and agents together so you can organize and track progress.')">
                <x-slot:icon>
                    <flux:icon.folder class="size-10 text-zinc-400 dark:text-zinc-500" />
                </x-slot:icon>
            </x-empty-state>
        @else
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search projects...') }}" icon="magnifying-glass" />

            <flux:table :paginate="$this->projects">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                        {{ __('Name') }}
                    </flux:table.column>
                    <flux:table.column>{{ __('Organization') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->projects as $project)
                        <flux:table.row :key="$project->id">
                            <flux:table.cell>
                                <flux:link href="{{ route('projects.show', $project) }}" wire:navigate>
                                    {{ $project->name }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $project->organization->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button href="{{ route('projects.edit', $project) }}" wire:navigate size="sm">
                                        {{ __('Edit') }}
                                    </flux:button>

                                    <flux:modal.trigger :name="'delete-project-' . $project->id">
                                        <flux:button size="sm" variant="danger">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </flux:modal.trigger>

                                    <x-confirm-delete-modal
                                        :id="'delete-project-' . $project->id"
                                        :title="__('Delete Project')"
                                        :item-name="$project->name"
                                        :delete-id="$project->id"
                                    />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center">
                                {{ __('No projects match your search.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
