<?php

use App\Models\Skill;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('View Skill')] class extends Component {
    public Skill $skill;

    public function mount(Skill $skill): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($skill->organization_id), 403);

        $this->skill = $skill->load(['organization', 'agent', 'agents', 'repos']);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete');
    }

    public function delete(): void
    {
        $this->skill->delete();

        $this->redirect(route('skills.index'), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('skills.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $skill->name }}</flux:heading>
                <flux:badge :variant="$skill->enabled ? 'primary' : 'outline'" size="sm">
                    {{ $skill->enabled ? __('Enabled') : __('Disabled') }}
                </flux:badge>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('skills.edit', $skill) }}" wire:navigate>
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
                <flux:text>{{ $skill->organization->title }}</flux:text>
            </div>

            @if ($skill->description)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</flux:heading>
                    <flux:text>{{ $skill->description }}</flux:text>
                </div>
            @endif

            @if ($skill->argument_hint)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Argument Hint') }}</flux:heading>
                    <flux:text>{{ $skill->argument_hint }}</flux:text>
                </div>
            @endif

            @if ($skill->license)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('License') }}</flux:heading>
                    <flux:text>{{ $skill->license }}</flux:text>
                </div>
            @endif

            @if ($skill->path)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Path') }}</flux:heading>
                    <flux:text>{{ $skill->path }}</flux:text>
                </div>
            @endif

            @if ($skill->allowed_tools)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Allowed Tools') }}</flux:heading>
                    <flux:text>{{ implode(', ', $skill->allowed_tools) }}</flux:text>
                </div>
            @endif

            @if ($skill->provider)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</flux:heading>
                    <flux:text>{{ $skill->provider }}</flux:text>
                </div>
            @endif

            @if ($skill->model)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</flux:heading>
                    <flux:text>{{ $skill->model }}</flux:text>
                </div>
            @endif

            @if ($skill->context)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Context') }}</flux:heading>
                    <flux:text>{{ $skill->context }}</flux:text>
                </div>
            @endif

            @if ($skill->agent)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Primary Agent') }}</flux:heading>
                    <flux:link href="{{ route('agents.show', $skill->agent) }}" wire:navigate>
                        {{ $skill->agent->name }}
                    </flux:link>
                </div>
            @endif

            @if ($skill->source)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Source') }}</flux:heading>
                    <flux:text>{{ ucfirst($skill->source) }}</flux:text>
                </div>
            @endif

            @if ($skill->source_reference)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Source Reference') }}</flux:heading>
                    <flux:text>{{ $skill->source_reference }}</flux:text>
                </div>
            @endif

            @if ($skill->source_url)
                <div>
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Source URL') }}</flux:heading>
                    <flux:link href="{{ $skill->source_url }}" target="_blank">{{ $skill->source_url }}</flux:link>
                </div>
            @endif
        </div>

        @if ($skill->agents->isNotEmpty())
            <div class="max-w-xl space-y-2">
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Associated Agents') }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($skill->agents as $agent)
                        <flux:badge>
                            <flux:link href="{{ route('agents.show', $agent) }}" wire:navigate>{{ $agent->name }}</flux:link>
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($skill->repos->isNotEmpty())
            <div class="max-w-xl space-y-2">
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Associated Repos') }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($skill->repos as $repo)
                        <flux:badge>
                            <flux:link href="{{ route('repos.show', $repo) }}" wire:navigate>{{ $repo->name }}</flux:link>
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        @endif

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Skill') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $skill->name]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
