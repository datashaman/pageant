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

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'projects.show', 'project_id' => $project->id, 'project_name' => $project->name]) }}">
    <div class="space-y-8">
        <x-show-header
            :back-url="route('projects.index')"
            :title="$project->name"
            :edit-url="route('projects.edit', $project)"
        >
            <x-slot:delete>
                <flux:button variant="outline" wire:click="$dispatch('open-modal', { id: 'confirm-delete' })">
                    {{ __('Delete') }}
                </flux:button>
            </x-slot:delete>
        </x-show-header>

        {{-- Project overview --}}
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 px-6 py-5 dark:border-zinc-700 dark:bg-zinc-800/30">
            @if ($project->description)
                <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed">{{ $project->description }}</p>
            @endif
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                <flux:badge variant="outline" size="sm">{{ $project->organization->name }}</flux:badge>
                <span class="text-zinc-400 dark:text-zinc-500">·</span>
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Created') }} {{ $project->created_at->format('M j, Y') }}</span>
                @if ($project->updated_at->gt($project->created_at))
                    <span class="text-zinc-400 dark:text-zinc-500">·</span>
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Updated') }} {{ $project->updated_at->format('M j, Y') }}</span>
                @endif
            </div>
        </div>

        {{-- Summary stats --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $project->repos->count() }}</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Repos') }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $project->workItems->count() }}</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Work Items') }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $project->workItems->where('status', 'open')->count() }}</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Open') }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $project->workItems->where('status', 'closed')->count() }}</div>
                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Closed') }}</div>
            </div>
        </div>

        {{-- Repos --}}
        @if ($project->repos->isNotEmpty())
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Repositories') }}</flux:heading>
                    <flux:button size="sm" href="{{ route('projects.edit', $project) }}" wire:navigate variant="ghost">
                        {{ __('Edit') }}
                    </flux:button>
                </div>
                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/50">
                    @foreach ($project->repos as $repo)
                        <a href="{{ route('repos.show', $repo) }}" wire:navigate class="flex items-center justify-between px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                            <flux:icon.code-bracket class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                            <span class="min-w-0 flex-1 truncate px-3 font-medium">{{ $repo->display_name }}</span>
                            <span class="shrink-0 text-zinc-400 dark:text-zinc-500">
                                <flux:icon.chevron-right class="size-4" />
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Work items --}}
        @if ($project->workItems->isNotEmpty())
            <section>
                <flux:heading size="lg" class="mb-3">{{ __('Work Items') }}</flux:heading>
                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/50">
                    @foreach ($project->workItems->take(10) as $workItem)
                        <a href="{{ route('work-items.show', $workItem) }}" wire:navigate class="flex items-center gap-4 px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                            <flux:badge :variant="$workItem->isOpen() ? 'success' : 'default'" size="sm" class="shrink-0">
                                {{ ucfirst($workItem->status) }}
                            </flux:badge>
                            <span class="min-w-0 flex-1 truncate">{{ $workItem->title }}</span>
                            @if ($workItem->source_reference || $workItem->source_url)
                                <span class="shrink-0 text-sm text-zinc-400 dark:text-zinc-500">
                                    <x-source-link
                                        :source="$workItem->source"
                                        :source-reference="$workItem->source_reference"
                                        :source-url="$workItem->source_url"
                                    />
                                </span>
                            @endif
                            <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                        </a>
                    @endforeach
                </div>
                @if ($project->workItems->count() > 10)
                    <flux:link href="{{ route('work-items.index') }}" wire:navigate class="mt-3 inline-block text-sm">
                        {{ __('View all :count work items', ['count' => $project->workItems->count()]) }} →
                    </flux:link>
                @endif
            </section>
        @elseif ($project->repos->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-600">
                <flux:icon.folder class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="sm" class="mt-3 text-zinc-500 dark:text-zinc-400">{{ __('No repos or work items yet') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">{{ __('Edit this project to add repositories, then import work items from the Work Items page.') }}</flux:text>
                <flux:button href="{{ route('projects.edit', $project) }}" wire:navigate variant="primary" class="mt-4">
                    {{ __('Edit Project') }}
                </flux:button>
            </div>
        @endif

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Project') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $project->name]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
