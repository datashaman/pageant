<?php

use App\Models\Repo;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('View Repo')] class extends Component {
    public Repo $repo;

    public function mount(Repo $repo): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->repo->organization_id), 403);

        $this->repo->load(['organization', 'skills', 'agents', 'projects']);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete');
    }

    public function delete(): void
    {
        $this->repo->delete();

        $this->redirect(route('repos.index'), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'repos.show', 'repo_id' => $repo->id, 'repo_name' => $repo->name, 'repo_source' => $repo->source, 'repo_source_reference' => $repo->source_reference]) }}">
    <div class="space-y-6">
        <x-show-header
            :back-url="route('repos.index')"
            :title="$repo->display_name"
            :edit-url="route('repos.edit', $repo)"
        />

        <div class="max-w-xl space-y-4">
            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Organization') }}</flux:heading>
                <flux:text>{{ $repo->organization->name }}</flux:text>
            </div>

            @if ($repo->source_reference || $repo->source_url)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Source') }}</flux:heading>
                    <x-source-link
                        :source="$repo->source"
                        :source-reference="$repo->display_name"
                        :source-url="$repo->source_url"
                    />
                </div>
            @endif

            @if ($repo->setup_script)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Setup Script') }}</flux:heading>
                    <pre class="mt-1 overflow-x-auto rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-800"><code>{{ $repo->setup_script }}</code></pre>
                </div>
            @endif

            @if ($repo->skills->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Skills') }}</flux:heading>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach ($repo->skills as $skill)
                            <flux:link href="{{ route('skills.show', $skill) }}" wire:navigate>
                                {{ $skill->name }}
                            </flux:link>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($repo->agents->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Agents') }}</flux:heading>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach ($repo->agents as $agent)
                            <flux:link href="{{ route('agents.show', $agent) }}" wire:navigate>
                                {{ $agent->name }}
                            </flux:link>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($repo->projects->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Projects') }}</flux:heading>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach ($repo->projects as $project)
                            <flux:link href="{{ route('projects.show', $project) }}" wire:navigate>
                                {{ $project->name }}
                            </flux:link>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Repo') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $repo->name]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
