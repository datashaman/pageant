<?php

use App\Models\Repo;
use App\Models\WorkItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function repos(): Collection
    {
        $orgId = auth()->user()->currentOrganizationId();

        if (! $orgId) {
            return collect();
        }

        return Repo::where('organization_id', $orgId)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function activeWorkItems(): Collection
    {
        $orgId = auth()->user()->currentOrganizationId();

        if (! $orgId) {
            return collect();
        }

        return WorkItem::where('organization_id', $orgId)
            ->where('status', 'open')
            ->whereNotNull('source_reference')
            ->with('project')
            ->latest('updated_at')
            ->get()
            ->groupBy(fn (WorkItem $wi) => Str::before($wi->source_reference, '#'));
    }
};
?>

<div>
    <flux:sidebar.nav>
        @forelse ($this->repos as $repo)
            @php
                $repoRef = $repo->display_name;
                $workItems = $this->activeWorkItems->get($repoRef, collect());
            @endphp

            <flux:sidebar.group class="grid">
                <div class="flex items-center justify-between px-2 py-1">
                    <flux:sidebar.item
                        :href="route('repos.show', $repo)"
                        :current="request()->routeIs('repos.show') && request()->route('repo')?->id === $repo->id"
                        icon="folder-git-2"
                        wire:navigate
                        class="min-w-0 flex-1"
                    >
                        <span class="truncate">{{ $repo->name }}</span>
                    </flux:sidebar.item>
                </div>

                @foreach ($workItems as $workItem)
                    <flux:sidebar.item
                        :href="route('work-items.show', $workItem)"
                        :current="request()->routeIs('work-items.show') && request()->route('workItem')?->id === $workItem->id"
                        wire:navigate
                        class="pl-8 text-sm"
                    >
                        <span class="truncate">{{ Str::limit($workItem->title, 30) }}</span>
                    </flux:sidebar.item>
                @endforeach
            </flux:sidebar.group>
        @empty
            <div class="px-4 py-3">
                <flux:text class="text-xs text-zinc-400">{{ __('No repos yet.') }}</flux:text>
            </div>
        @endforelse
    </flux:sidebar.nav>

    <div class="px-4 py-2">
        <flux:button size="sm" variant="ghost" :href="route('repos.index')" wire:navigate class="w-full justify-start">
            <flux:icon.plus class="size-4" />
            {{ __('Add Repository') }}
        </flux:button>
    </div>
</div>
