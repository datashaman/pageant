<?php

use App\Models\Agent;
use App\Models\Skill;
use App\Models\Workspace;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function workspaceCount(): int
    {
        return Workspace::query()->forCurrentOrganization()->count();
    }

    #[Computed]
    public function agentCount(): int
    {
        return Agent::query()->forCurrentOrganization()->count();
    }

    #[Computed]
    public function skillCount(): int
    {
        return Skill::query()->forCurrentOrganization()->count();
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'dashboard']) }}">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('workspaces.index') }}" wire:navigate class="group rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-700/80">
                <div class="flex items-center gap-3">
                    <flux:icon name="squares-2x2" variant="outline" class="size-5 text-zinc-400 transition group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" />
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Workspaces') }}</flux:heading>
                </div>
                <flux:heading size="lg" class="mt-2">{{ $this->workspaceCount }}</flux:heading>
            </a>

            <a href="{{ route('agents.index') }}" wire:navigate class="group rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-700/80">
                <div class="flex items-center gap-3">
                    <flux:icon name="cpu-chip" variant="outline" class="size-5 text-zinc-400 transition group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" />
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Agents') }}</flux:heading>
                </div>
                <flux:heading size="lg" class="mt-2">{{ $this->agentCount }}</flux:heading>
            </a>

            <a href="{{ route('skills.index') }}" wire:navigate class="group rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-700/80">
                <div class="flex items-center gap-3">
                    <flux:icon name="bolt" variant="outline" class="size-5 text-zinc-400 transition group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" />
                    <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Skills') }}</flux:heading>
                </div>
                <flux:heading size="lg" class="mt-2">{{ $this->skillCount }}</flux:heading>
            </a>
        </div>
    </div>
</div>
