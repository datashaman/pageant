<?php

use App\Events\WorkItemCreated;
use App\Jobs\ReconcileWorkItemStatuses;
use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Work Items')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $sortField = 'title';
    public string $sortDirection = 'asc';
    public string $statusFilter = 'open';

    public bool $showImportModal = false;
    public string $selectedRepoId = '';
    public bool $issuesLoaded = false;
    public bool $syncing = false;

    public function mount(): void
    {
        $org = auth()->user()->currentOrganization;

        if ($org) {
            ReconcileWorkItemStatuses::dispatchSync($org);
        }
    }

    public function syncStatuses(): void
    {
        $this->syncing = true;

        $org = auth()->user()->currentOrganization;

        if ($org) {
            ReconcileWorkItemStatuses::dispatchSync($org);
        }

        unset($this->workItems);
        $this->syncing = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /** @var list<string> */
    private const SORTABLE_FIELDS = ['title'];

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE_FIELDS, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function workItems(): LengthAwarePaginator
    {
        return WorkItem::query()
            ->forCurrentOrganization()
            ->with(['organization', 'project'])
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function trackedRepos(): \Illuminate\Database\Eloquent\Collection
    {
        return Repo::query()
            ->forCurrentOrganization()
            ->where('source', 'github')
            ->with('organization')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function trackedIssueKeys(): array
    {
        return WorkItem::query()
            ->forCurrentOrganization()
            ->where('source', 'github')
            ->pluck('source_reference')
            ->toArray();
    }

    #[Computed]
    public function githubIssues(): array
    {
        if (! $this->issuesLoaded || ! $this->selectedRepoId) {
            return [];
        }

        return cache()->remember(
            'github_issues_' . $this->selectedRepoId,
            now()->addMinutes(5),
            function () {
                $repo = $this->trackedRepos->firstWhere('id', $this->selectedRepoId);

                if (! $repo || ! $repo->source_reference) {
                    return [];
                }

                $installation = GithubInstallation::query()
                    ->where('organization_id', $repo->organization_id)
                    ->first();

                if (! $installation) {
                    return [];
                }

                return app(GitHubService::class)->listIssues($installation, $repo->source_reference);
            }
        );
    }

    public function openImportModal(): void
    {
        $this->showImportModal = true;
        $this->issuesLoaded = false;

        if ($this->trackedRepos->count() === 1) {
            $this->selectedRepoId = (string) $this->trackedRepos->first()->id;
            $this->issuesLoaded = true;
        } else {
            $this->selectedRepoId = '';
        }
    }

    public function updatedSelectedRepoId(): void
    {
        $this->issuesLoaded = (bool) $this->selectedRepoId;
    }

    public function trackIssue(int $number, string $title, string $htmlUrl): void
    {
        $repo = $this->trackedRepos->firstWhere('id', $this->selectedRepoId);

        if (! $repo) {
            return;
        }

        $sourceReference = $repo->source_reference . '#' . $number;

        $workItem = WorkItem::query()->firstOrCreate(
            [
                'organization_id' => $repo->organization_id,
                'source' => 'github',
                'source_reference' => $sourceReference,
            ],
            [
                'title' => $title,
                'source_url' => $htmlUrl,
                'description' => '',
                'board_id' => '',
            ]
        );

        if ($workItem->wasRecentlyCreated) {
            $installation = GithubInstallation::query()
                ->where('organization_id', $repo->organization_id)
                ->first();
            if ($installation) {
                WorkItemCreated::dispatch($workItem, $repo->source_reference, $installation->installation_id);
            }
        }

        unset($this->trackedIssueKeys);
    }

    public function untrackIssue(string $sourceReference): void
    {
        WorkItem::query()
            ->forCurrentOrganization()
            ->where('source', 'github')
            ->where('source_reference', $sourceReference)
            ->delete();

        unset($this->trackedIssueKeys);
    }

    public function confirmClose(string $id): void
    {
        $this->dispatch('open-modal', id: 'confirm-close-' . $id);
    }

    public function close(string $id): void
    {
        $workItem = WorkItem::query()->forCurrentOrganization()->findOrFail($id);
        $workItem->update(['status' => 'closed']);

        unset($this->workItems);
        $this->dispatch('close-modal', id: 'confirm-close-' . $id);
    }

    public function reopen(string $id): void
    {
        $workItem = WorkItem::query()->forCurrentOrganization()->findOrFail($id);
        $workItem->update(['status' => 'open']);

        unset($this->workItems);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'work-items.index']) }}">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Work Items') }}</flux:heading>
            <div class="flex items-center gap-2">
                <flux:button wire:click="syncStatuses" wire:loading.attr="disabled" wire:target="syncStatuses">
                    <div class="flex items-center gap-1.5">
                        <flux:icon.arrow-path class="size-4" wire:loading.class="animate-spin" wire:target="syncStatuses" />
                        {{ __('Sync') }}
                    </div>
                </flux:button>
                <flux:button variant="primary" wire:click="openImportModal">
                    {{ __('Import Issues') }}
                </flux:button>
            </div>
        </div>

        @if ($this->workItems->isEmpty() && ! $this->search && $this->statusFilter === 'open')
            <x-empty-state :heading="__('No work items yet')" :description="__('Work items track GitHub issues and tasks that agents can pick up and work on. Import issues from your tracked repos to get started.')">
                <x-slot:icon>
                    <flux:icon.clipboard-document-list class="size-10 text-zinc-400 dark:text-zinc-500" />
                </x-slot:icon>
            </x-empty-state>
        @else
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <flux:input wire:model.live="search" placeholder="{{ __('Search work items...') }}" icon="magnifying-glass" />
                </div>
                <flux:select wire:model.live="statusFilter" class="w-40">
                    <flux:select.option value="open">{{ __('Open') }}</flux:select.option>
                    <flux:select.option value="closed">{{ __('Closed') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:table :paginate="$this->workItems">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'title'" :direction="$sortDirection" wire:click="sortBy('title')">
                        {{ __('Title') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Issue') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Status') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Project') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Organization') }}
                    </flux:table.column>
                    <flux:table.column align="end">
                        {{ __('Actions') }}
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->workItems as $workItem)
                        <flux:table.row :key="$workItem->id">
                            <flux:table.cell>
                                <flux:link href="{{ route('work-items.show', $workItem) }}" wire:navigate>
                                    {{ $workItem->title }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($workItem->source_reference || $workItem->source_url)
                                    <x-source-link
                                        :source="$workItem->source"
                                        :source-reference="$workItem->source_reference"
                                        :source-url="$workItem->source_url"
                                    />
                                @else
                                    &mdash;
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :variant="$workItem->isOpen() ? 'success' : 'default'" size="sm">
                                    {{ ucfirst($workItem->status) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($workItem->project)
                                    <flux:link href="{{ route('projects.show', $workItem->project) }}" wire:navigate>
                                        {{ $workItem->project->name }}
                                    </flux:link>
                                @else
                                    &mdash;
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $workItem->organization->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" href="{{ route('work-items.edit', $workItem) }}" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:button>
                                    @if ($workItem->isOpen())
                                        <flux:button size="sm" variant="outline" wire:click="confirmClose('{{ $workItem->id }}')" wire:target="confirmClose('{{ $workItem->id }}')">
                                            {{ __('Close') }}
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" wire:click="reopen('{{ $workItem->id }}')" wire:target="reopen('{{ $workItem->id }}')">
                                            {{ __('Reopen') }}
                                        </flux:button>
                                    @endif
                                </div>

                                <flux:modal name="confirm-close-{{ $workItem->id }}">
                                    <div class="space-y-6">
                                        <flux:heading size="lg">{{ __('Close Work Item') }}</flux:heading>
                                        <flux:text>{{ __('Are you sure you want to close ":title"?', ['title' => $workItem->title]) }}</flux:text>
                                        <div class="flex justify-end gap-3">
                                            <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                                            <flux:button variant="danger" wire:click="close('{{ $workItem->id }}')" wire:target="close('{{ $workItem->id }}')">{{ __('Close') }}</flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center">
                                {{ __('No work items match your search.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal wire:model="showImportModal" variant="flyout" class="w-[32rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Import Issues from GitHub') }}</flux:heading>

            @if ($this->trackedRepos->isEmpty())
                <flux:text>{{ __('No tracked repos found. Track repos from the Repos page first.') }}</flux:text>
            @else
                @if ($this->trackedRepos->count() > 1)
                    <flux:select wire:model.live="selectedRepoId" :label="__('Repository')">
                        <flux:select.option value="">{{ __('Select repository...') }}</flux:select.option>
                        @foreach ($this->trackedRepos as $repo)
                            <flux:select.option :value="$repo->id">
                                {{ $repo->source_reference }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                @if ($selectedRepoId && count($this->githubIssues) > 0)
                    @php
                        $selectedRepo = $this->trackedRepos->firstWhere('id', $selectedRepoId);
                        $repoRef = $selectedRepo?->source_reference ?? '';
                    @endphp
                    <div x-data="{
                        filter: '',
                        issues: @js($this->githubIssues),
                        tracked: @js($this->trackedIssueKeys),
                        repoRef: @js($repoRef),
                        visibleCount: 30,
                        issueKey(issue) {
                            return this.repoRef + '#' + issue.number;
                        },
                        get filtered() {
                            if (!this.filter) return this.issues;
                            const q = this.filter.toLowerCase();
                            return this.issues.filter(i =>
                                i.title.toLowerCase().includes(q) ||
                                String(i.number).includes(q)
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
                         x-on:issue-tracked.window="tracked = [...tracked, $event.detail.sourceReference]"
                         x-on:issue-untracked.window="tracked = tracked.filter(r => r !== $event.detail.sourceReference)">
                        <flux:input x-model="filter" x-on:input="visibleCount = 30" placeholder="{{ __('Filter issues...') }}" icon="magnifying-glass" />

                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-2" x-text="filtered.length + ' issues'"></div>

                        <div class="mt-2 max-h-[70vh] space-y-1 overflow-y-auto">
                            <template x-for="issue in visible" :key="issue.number">
                                <div class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium">
                                            <span class="text-zinc-400 dark:text-zinc-500" x-text="'#' + issue.number"></span>
                                            <span x-text="issue.title"></span>
                                        </div>
                                        <div class="flex gap-1 mt-1" x-show="issue.labels && issue.labels.length">
                                            <template x-for="label in issue.labels" :key="label.id">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :style="'background-color: #' + label.color + '30; color: #' + label.color" x-text="label.name"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="ml-3 shrink-0">
                                        <div x-show="tracked.includes(issueKey(issue))">
                                            <flux:button size="sm" variant="danger" x-on:click="$wire.untrackIssue(issueKey(issue)); $dispatch('issue-untracked', { sourceReference: issueKey(issue) })">
                                                {{ __('Untrack') }}
                                            </flux:button>
                                        </div>
                                        <div x-show="!tracked.includes(issueKey(issue))">
                                            <flux:button size="sm" variant="primary" x-on:click="$wire.trackIssue(issue.number, issue.title, issue.html_url); $dispatch('issue-tracked', { sourceReference: issueKey(issue) })">
                                                {{ __('Track') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div x-show="hasMore" x-intersect="loadMore()" class="py-2 text-center">
                                <flux:text class="text-xs">{{ __('Loading more...') }}</flux:text>
                            </div>
                        </div>
                    </div>
                @elseif ($selectedRepoId && count($this->githubIssues) === 0)
                    <flux:text>{{ __('No open issues found for this repository.') }}</flux:text>
                @endif
            @endif
        </div>
    </flux:modal>
</div>
