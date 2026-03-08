<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use App\Models\WorkItem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function agentCount(): int
    {
        return Agent::query()->forCurrentOrganization()->count();
    }

    #[Computed]
    public function repoCount(): int
    {
        return Repo::query()->forCurrentOrganization()->count();
    }

    #[Computed]
    public function skillCount(): int
    {
        return Skill::query()->forCurrentOrganization()->count();
    }

    #[Computed]
    public function projectCount(): int
    {
        return Project::query()->forCurrentOrganization()->count();
    }

    #[Computed]
    public function workItemCount(): int
    {
        return WorkItem::query()->forCurrentOrganization()->count();
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'dashboard']) }}">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('projects.index') }}" wire:navigate class="rounded-xl border border-neutral-200 p-6 transition hover:bg-zinc-50 dark:border-neutral-700 dark:hover:bg-zinc-700/50">
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Projects') }}</flux:heading>
                <flux:heading size="xl" class="mt-2">{{ $this->projectCount }}</flux:heading>
            </a>

            <a href="{{ route('repos.index') }}" wire:navigate class="rounded-xl border border-neutral-200 p-6 transition hover:bg-zinc-50 dark:border-neutral-700 dark:hover:bg-zinc-700/50">
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Repos') }}</flux:heading>
                <flux:heading size="xl" class="mt-2">{{ $this->repoCount }}</flux:heading>
            </a>

            <a href="{{ route('work-items.index') }}" wire:navigate class="rounded-xl border border-neutral-200 p-6 transition hover:bg-zinc-50 dark:border-neutral-700 dark:hover:bg-zinc-700/50">
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Work Items') }}</flux:heading>
                <flux:heading size="xl" class="mt-2">{{ $this->workItemCount }}</flux:heading>
            </a>

            <a href="{{ route('agents.index') }}" wire:navigate class="rounded-xl border border-neutral-200 p-6 transition hover:bg-zinc-50 dark:border-neutral-700 dark:hover:bg-zinc-700/50">
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Agents') }}</flux:heading>
                <flux:heading size="xl" class="mt-2">{{ $this->agentCount }}</flux:heading>
            </a>

            <a href="{{ route('skills.index') }}" wire:navigate class="rounded-xl border border-neutral-200 p-6 transition hover:bg-zinc-50 dark:border-neutral-700 dark:hover:bg-zinc-700/50">
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Skills') }}</flux:heading>
                <flux:heading size="xl" class="mt-2">{{ $this->skillCount }}</flux:heading>
            </a>
        </div>
    </div>
</div>
