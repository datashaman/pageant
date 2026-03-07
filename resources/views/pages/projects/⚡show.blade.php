<?php

use App\Models\Project;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Project')] class extends Component {
    public Project $project;

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()->organizations->contains('id', $project->organization_id), 403);

        $this->project = $project->load(['organization', 'repos', 'workItems']);
    }

    public function delete(): void
    {
        abort_unless(auth()->user()->organizations->contains('id', $this->project->organization_id), 403);

        $this->project->delete();

        $this->redirect(route('projects.index'), navigate: true);
    }
}; ?>

<div>name">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $project->name }}</flux:heading>

            <div class="flex gap-2">
                <flux:button href="{{ route('projects.edit', $project) }}" wire:navigate variant="primary">
                    {{ __('Edit') }}
                </flux:button>

                <flux:modal.trigger name="delete-project">
                    <flux:button variant="danger">{{ __('Delete') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Organization') }}</dt>
                    <dd class="mt-1">
                        <a href="{{ route('organizations.show', $project->organization) }}" wire:navigate class="hover:underline">
                            {{ $project->organization->name }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</dt>
                    <dd class="mt-1">{{ $project->description ?: __('No description provided.') }}</dd>
                </div>
            </dl>
        </div>

        @if ($project->repos->isNotEmpty())
            <div>
                <flux:heading size="lg" class="mb-3">{{ __('Repos') }}</flux:heading>
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($project->repos as $repo)
                                <flux:table.row :key="$repo->id">
                                    <flux:table.cell>
                                        <a href="{{ route('repos.show', $repo) }}" wire:navigate class="hover:underline">
                                            {{ $repo->name }}
                                        </a>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        @endif

        @if ($project->workItems->isNotEmpty())
            <div>
                <flux:heading size="lg" class="mb-3">{{ __('Work Items') }}</flux:heading>
                <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Title') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($project->workItems as $workItem)
                                <flux:table.row :key="$workItem->id">
                                    <flux:table.cell>
                                        <a href="{{ route('work-items.show', $workItem) }}" wire:navigate class="hover:underline">
                                            {{ $workItem->title }}
                                        </a>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        @endif

        <flux:modal name="delete-project">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Project') }}</flux:heading>
                <p>{{ __('Are you sure you want to delete :name? This action cannot be undone.', ['name' => $project->name]) }}</p>
                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="delete" variant="danger">
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
