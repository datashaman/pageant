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

    public function sort(string $column): void
    {
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
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="hover:underline">
                                {{ $project->name }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $project->organization->name }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button href="{{ route('projects.edit', $project) }}" wire:navigate size="sm">
                                    {{ __('Edit') }}
                                </flux:button>

                                <flux:modal.trigger :name="'delete-project-' . $project->id">
                                    <flux:button size="sm" variant="ghost">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal :name="'delete-project-' . $project->id">
                                    <div class="space-y-6">
                                        <flux:heading size="lg">{{ __('Delete Project') }}</flux:heading>
                                        <p>{{ __('Are you sure you want to delete :name? This action cannot be undone.', ['name' => $project->name]) }}</p>
                                        <div class="flex gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button wire:click="delete('{{ $project->id }}')" variant="danger">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center">
                            {{ __('No projects found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
