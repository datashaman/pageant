<?php

use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Services\GitHubService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Repos')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public bool $showImportModal = false;
    public string $selectedInstallationId = '';
    public bool $reposLoaded = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function repos(): LengthAwarePaginator
    {
        return Repo::query()
            ->forCurrentOrganization()
            ->with('organization')
            ->when($this->search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function installations(): \Illuminate\Database\Eloquent\Collection
    {
        $orgIds = auth()->user()->organizations()->pluck('organizations.id');

        return GithubInstallation::query()
            ->whereIn('organization_id', $orgIds)
            ->with('organization')
            ->get();
    }

    #[Computed]
    public function trackedRepoKeys(): array
    {
        return Repo::query()
            ->forCurrentOrganization()
            ->where('source', 'github')
            ->pluck('source_reference')
            ->toArray();
    }

    #[Computed]
    public function githubRepos(): array
    {
        if (! $this->reposLoaded || ! $this->selectedInstallationId) {
            return [];
        }

        return cache()->remember(
            'github_repos_' . $this->selectedInstallationId,
            now()->addMinutes(5),
            function () {
                $installation = $this->installations->firstWhere('id', $this->selectedInstallationId);

                if (! $installation) {
                    return [];
                }

                return app(GitHubService::class)->listRepositories($installation);
            }
        );
    }

    public function openImportModal(): void
    {
        $this->showImportModal = true;
        $this->reposLoaded = false;

        if ($this->installations->count() === 1) {
            $this->selectedInstallationId = (string) $this->installations->first()->id;
            $this->reposLoaded = true;
        } else {
            $this->selectedInstallationId = '';
        }
    }

    public function updatedSelectedInstallationId(): void
    {
        $this->reposLoaded = (bool) $this->selectedInstallationId;
    }

    public function trackRepo(string $fullName, string $htmlUrl): void
    {
        $installation = $this->installations->firstWhere('id', $this->selectedInstallationId);

        if (! $installation) {
            return;
        }

        Repo::query()->firstOrCreate(
            [
                'organization_id' => $installation->organization_id,
                'source' => 'github',
                'source_reference' => $fullName,
            ],
            [
                'name' => Str::afterLast($fullName, '/'),
                'source_url' => $htmlUrl,
            ]
        );

        unset($this->trackedRepoKeys);
    }

    public function untrackRepo(string $fullName): void
    {
        Repo::query()
            ->forCurrentOrganization()
            ->where('source', 'github')
            ->where('source_reference', $fullName)
            ->delete();

        unset($this->trackedRepoKeys);
    }

    public function confirmDelete(string $id): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete-' . $id);
    }

    public function delete(string $id): void
    {
        $repo = Repo::query()->forCurrentOrganization()->findOrFail($id);
        $repo->delete();

        $this->dispatch('close-modal', id: 'confirm-delete-' . $id);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'repos.index']) }}">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Repos') }}</flux:heading>
            <flux:button variant="primary" wire:click="openImportModal">
                {{ __('Import Repo') }}
            </flux:button>
        </div>

        <flux:input wire:model.live="search" placeholder="{{ __('Search repos...') }}" icon="magnifying-glass" />

        <flux:table :paginate="$this->repos">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">
                    {{ __('Name') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Repository') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Organization') }}
                </flux:table.column>
                <flux:table.column align="end">
                    {{ __('Actions') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->repos as $repo)
                    <flux:table.row :key="$repo->id">
                        <flux:table.cell>
                            <flux:link href="{{ route('repos.show', $repo) }}" wire:navigate>
                                {{ $repo->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($repo->source_url)
                                <flux:link href="{{ $repo->source_url }}" target="_blank">
                                    {{ $repo->source_reference }}
                                </flux:link>
                            @else
                                {{ $repo->source_reference }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $repo->organization->name }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" href="{{ route('repos.edit', $repo) }}" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="confirmDelete('{{ $repo->id }}')">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>

                            <flux:modal name="confirm-delete-{{ $repo->id }}">
                                <div class="space-y-6">
                                    <flux:heading size="lg">{{ __('Delete Repo') }}</flux:heading>
                                    <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $repo->name]) }}</flux:text>
                                    <div class="flex justify-end gap-3">
                                        <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="danger" wire:click="delete('{{ $repo->id }}')">{{ __('Delete') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">
                            {{ __('No repos found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showImportModal" variant="flyout" class="w-[32rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Import Repo from GitHub') }}</flux:heading>

            @if ($this->installations->isEmpty())
                <flux:text>{{ __('No GitHub App installations found. Install the GitHub App on your account or organization first.') }}</flux:text>
            @else
                @if ($this->installations->count() > 1)
                    <flux:select wire:model.live="selectedInstallationId" :label="__('Installation')">
                        <flux:select.option value="">{{ __('Select installation...') }}</flux:select.option>
                        @foreach ($this->installations as $installation)
                            <flux:select.option :value="$installation->id">
                                {{ $installation->account_login }} ({{ $installation->account_type }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                @if ($selectedInstallationId && count($this->githubRepos) > 0)
                    <div x-data="{
                        filter: '',
                        repos: @js($this->githubRepos),
                        tracked: @js($this->trackedRepoKeys),
                        visibleCount: 30,
                        get filtered() {
                            if (!this.filter) return this.repos;
                            const q = this.filter.toLowerCase();
                            return this.repos.filter(r =>
                                r.full_name.toLowerCase().includes(q) ||
                                (r.description && r.description.toLowerCase().includes(q))
                            );
                        },
                        get visible() {
                            return this.filtered.slice(0, this.visibleCount);
                        },
                        get hasMore() {
                            return this.visibleCount < this.filtered.length;
                        },
                        loadMore() {
                            this.visibleCount += 30;
                        },
                    }"
                         x-on:repo-tracked.window="tracked = [...tracked, $event.detail.fullName]"
                         x-on:repo-untracked.window="tracked = tracked.filter(r => r !== $event.detail.fullName)">
                        <flux:input x-model="filter" x-on:input="visibleCount = 30" placeholder="{{ __('Filter repositories...') }}" icon="magnifying-glass" />

                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-2" x-text="filtered.length + ' repositories'"></div>

                        <div class="mt-2 max-h-[70vh] space-y-1 overflow-y-auto">
                            <template x-for="repo in visible" :key="repo.full_name">
                                <div class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium" x-text="repo.full_name"></div>
                                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400" x-show="repo.description" x-text="repo.description"></div>
                                    </div>
                                    <div class="ml-3 shrink-0">
                                        <template x-if="tracked.includes(repo.full_name)">
                                            <flux:button size="sm" variant="ghost" x-on:click="$wire.untrackRepo(repo.full_name); $dispatch('repo-untracked', { fullName: repo.full_name })">
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </template>
                                        <template x-if="!tracked.includes(repo.full_name)">
                                            <flux:button size="sm" variant="primary" x-on:click="$wire.trackRepo(repo.full_name, repo.html_url); $dispatch('repo-tracked', { fullName: repo.full_name })">
                                                {{ __('Track') }}
                                            </flux:button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div x-show="hasMore" x-intersect="loadMore()" class="py-2 text-center">
                                <flux:text class="text-xs">{{ __('Loading more...') }}</flux:text>
                            </div>
                        </div>
                    </div>
                @elseif ($selectedInstallationId && count($this->githubRepos) === 0)
                    <flux:text>{{ __('No repositories found for this installation.') }}</flux:text>
                @endif
            @endif
        </div>
    </flux:modal>
</div>
