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

<div>name">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('repos.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $repo->name }}</flux:heading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('repos.edit', $repo) }}" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button variant="danger" wire:click="confirmDelete">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>

        <div class="max-w-xl space-y-4">
            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Organization') }}</flux:heading>
                <flux:link href="{{ route('organizations.show', $repo->organization) }}" wire:navigate>
                    {{ $repo->organization->title }}
                </flux:link>
            </div>

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Source') }}</flux:heading>
                <flux:text>{{ $repo->source }}</flux:text>
            </div>

            @if ($repo->source_reference)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Source Reference') }}</flux:heading>
                    <flux:text>{{ $repo->source_reference }}</flux:text>
                </div>
            @endif

            @if ($repo->source_url)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Source URL') }}</flux:heading>
                    <flux:link href="{{ $repo->source_url }}" target="_blank">{{ $repo->source_url }}</flux:link>
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
