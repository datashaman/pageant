<?php

use App\Models\WorkItem;
use App\Services\WorktreeBrowser;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public WorkItem $workItem;

    public bool $panelOpen = false;

    public string $activeTab = 'changes';

    public string $diffMode = 'base';

    public string $currentDirectory = '';

    public string $fileSearch = '';

    public ?string $viewingFile = null;

    public ?string $fileContents = null;

    public function mount(WorkItem $workItem): void
    {
        $this->workItem = $workItem;
    }

    public function togglePanel(): void
    {
        $this->panelOpen = ! $this->panelOpen;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->viewingFile = null;
        $this->fileContents = null;
    }

    public function setDiffMode(string $mode): void
    {
        $this->diffMode = $mode;
    }

    public function navigateToDirectory(string $directory): void
    {
        $this->currentDirectory = $directory;
        $this->viewingFile = null;
        $this->fileContents = null;
    }

    public function navigateUp(): void
    {
        $this->currentDirectory = dirname($this->currentDirectory) === '.'
            ? ''
            : dirname($this->currentDirectory);
        $this->viewingFile = null;
        $this->fileContents = null;
    }

    public function viewFile(string $filePath): void
    {
        $browser = app(WorktreeBrowser::class);
        $contents = $browser->getFileContents($this->workItem, $filePath);

        if ($contents !== null) {
            $this->viewingFile = $filePath;
            $this->fileContents = $contents;
        }
    }

    public function closeFile(): void
    {
        $this->viewingFile = null;
        $this->fileContents = null;
    }

    #[Computed]
    public function hasWorktree(): bool
    {
        return app(WorktreeBrowser::class)->hasWorktree($this->workItem);
    }

    #[Computed]
    public function diffData(): array
    {
        if (! $this->hasWorktree) {
            return ['diff' => '', 'stats' => ['files_changed' => 0, 'insertions' => 0, 'deletions' => 0]];
        }

        return app(WorktreeBrowser::class)->getDiff($this->workItem, $this->diffMode);
    }

    #[Computed]
    public function changedFiles(): array
    {
        if (! $this->hasWorktree) {
            return [];
        }

        return app(WorktreeBrowser::class)->getChangedFiles($this->workItem);
    }

    #[Computed]
    public function fileTree(): array
    {
        if (! $this->hasWorktree) {
            return [];
        }

        return app(WorktreeBrowser::class)->getFileTree($this->workItem, $this->currentDirectory);
    }

    #[Computed]
    public function filteredFileTree(): array
    {
        $tree = $this->fileTree;

        if (empty($this->fileSearch)) {
            return $tree;
        }

        $search = strtolower($this->fileSearch);

        return array_values(array_filter($tree, function (array $entry) use ($search): bool {
            return str_contains(strtolower($entry['name']), $search);
        }));
    }

    #[Computed]
    public function changedFilePaths(): array
    {
        return array_column($this->changedFiles, 'path');
    }
}; ?>

<div>
    {{-- Toggle button --}}
    <div class="fixed right-0 top-1/2 -translate-y-1/2 z-40" x-show="!@js($panelOpen)">
        <flux:button
            size="sm"
            wire:click="togglePanel"
            class="rounded-r-none rounded-l-lg shadow-lg"
        >
            <flux:icon.code-bracket class="size-4" />
        </flux:button>
    </div>

    {{-- Panel --}}
    @if ($panelOpen)
        <div class="fixed right-0 top-0 h-full w-full max-w-lg z-50 flex flex-col bg-white dark:bg-zinc-900 border-l border-zinc-200 dark:border-zinc-700 shadow-xl"
             x-data
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="togglePanel">
                        <flux:icon.x-mark class="size-4" />
                    </flux:button>
                    <flux:heading size="base">{{ __('Repository') }}</flux:heading>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
                <button
                    wire:click="setTab('changes')"
                    class="flex-1 px-4 py-2 text-sm font-medium text-center transition-colors {{ $activeTab === 'changes' ? 'text-zinc-900 dark:text-zinc-100 border-b-2 border-zinc-900 dark:border-zinc-100' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    {{ __('Changes') }}
                    @if ($this->hasWorktree && $this->diffData['stats']['files_changed'] > 0)
                        <flux:badge size="sm" class="ml-1">{{ $this->diffData['stats']['files_changed'] }}</flux:badge>
                    @endif
                </button>
                <button
                    wire:click="setTab('files')"
                    class="flex-1 px-4 py-2 text-sm font-medium text-center transition-colors {{ $activeTab === 'files' ? 'text-zinc-900 dark:text-zinc-100 border-b-2 border-zinc-900 dark:border-zinc-100' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    {{ __('Files') }}
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto">
                @if (! $this->hasWorktree)
                    <div class="p-6">
                        <x-empty-state>
                            <x-slot:icon>
                                <flux:icon.code-bracket class="size-8 text-zinc-400" />
                            </x-slot:icon>
                            <x-slot:heading>{{ __('No Worktree') }}</x-slot:heading>
                            <x-slot:description>{{ __('This work item does not have an active worktree. A worktree is created when a plan is executed.') }}</x-slot:description>
                        </x-empty-state>
                    </div>
                @elseif ($activeTab === 'changes')
                    {{-- Changes tab --}}
                    <div class="p-4 space-y-4">
                        {{-- Diff mode toggle --}}
                        <div class="flex items-center gap-2">
                            <flux:button
                                size="sm"
                                :variant="$diffMode === 'local' ? 'primary' : 'ghost'"
                                wire:click="setDiffMode('local')"
                            >
                                {{ __('Local') }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                :variant="$diffMode === 'base' ? 'primary' : 'ghost'"
                                wire:click="setDiffMode('base')"
                            >
                                {{ __('Base') }}
                            </flux:button>
                        </div>

                        {{-- Stats --}}
                        @if ($this->diffData['stats']['files_changed'] > 0)
                            <div class="flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __(':count file(s) changed', ['count' => $this->diffData['stats']['files_changed']]) }}</span>
                                @if ($this->diffData['stats']['insertions'] > 0)
                                    <span class="text-green-600 dark:text-green-400">+{{ $this->diffData['stats']['insertions'] }}</span>
                                @endif
                                @if ($this->diffData['stats']['deletions'] > 0)
                                    <span class="text-red-600 dark:text-red-400">-{{ $this->diffData['stats']['deletions'] }}</span>
                                @endif
                            </div>
                        @endif

                        {{-- Changed files list --}}
                        @if (count($this->changedFiles) > 0)
                            <div class="space-y-1">
                                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Changed Files') }}</flux:heading>
                                @foreach ($this->changedFiles as $file)
                                    <button
                                        wire:click="viewFile('{{ $file['path'] }}')"
                                        wire:key="changed-{{ $loop->index }}"
                                        class="flex items-center gap-2 w-full text-left px-2 py-1 rounded text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                    >
                                        @php
                                            $statusColor = match($file['status']) {
                                                'added' => 'text-green-600 dark:text-green-400',
                                                'deleted' => 'text-red-600 dark:text-red-400',
                                                'renamed' => 'text-blue-600 dark:text-blue-400',
                                                default => 'text-yellow-600 dark:text-yellow-400',
                                            };
                                            $statusLabel = match($file['status']) {
                                                'added' => 'A',
                                                'deleted' => 'D',
                                                'renamed' => 'R',
                                                'modified' => 'M',
                                                default => '?',
                                            };
                                        @endphp
                                        <span class="font-mono text-xs font-bold {{ $statusColor }}">{{ $statusLabel }}</span>
                                        <span class="font-mono text-xs truncate text-zinc-700 dark:text-zinc-300">{{ $file['path'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Diff output --}}
                        @if ($this->diffData['diff'])
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                <pre class="p-3 text-xs font-mono overflow-x-auto bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 max-h-96 whitespace-pre">{{ $this->diffData['diff'] }}</pre>
                            </div>
                        @else
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No changes found.') }}</flux:text>
                        @endif
                    </div>
                @elseif ($activeTab === 'files')
                    {{-- Files tab --}}
                    <div class="p-4 space-y-3">
                        {{-- Search --}}
                        <flux:input
                            type="text"
                            wire:model.live.debounce.300ms="fileSearch"
                            placeholder="{{ __('Search files...') }}"
                            icon="magnifying-glass"
                            size="sm"
                        />

                        {{-- File viewer --}}
                        @if ($viewingFile)
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="closeFile">
                                        <flux:icon.arrow-left class="size-4" />
                                    </flux:button>
                                    <span class="font-mono text-xs text-zinc-700 dark:text-zinc-300 truncate">{{ $viewingFile }}</span>
                                </div>
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                    <pre class="p-3 text-xs font-mono overflow-x-auto bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 max-h-96 whitespace-pre">{{ $fileContents }}</pre>
                                </div>
                            </div>
                        @else
                            {{-- Breadcrumb --}}
                            @if ($currentDirectory)
                                <div class="flex items-center gap-1 text-xs">
                                    <button wire:click="navigateToDirectory('')" class="text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300">
                                        <flux:icon.home class="size-3.5" />
                                    </button>
                                    @foreach (explode('/', $currentDirectory) as $i => $segment)
                                        <span class="text-zinc-400 dark:text-zinc-500">/</span>
                                        @php
                                            $breadcrumbPath = implode('/', array_slice(explode('/', $currentDirectory), 0, $i + 1));
                                        @endphp
                                        <button
                                            wire:click="navigateToDirectory('{{ $breadcrumbPath }}')"
                                            class="text-zinc-600 dark:text-zinc-300 hover:text-zinc-800 dark:hover:text-zinc-100"
                                        >
                                            {{ $segment }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Directory listing --}}
                            <div class="space-y-0.5">
                                @if ($currentDirectory)
                                    <button
                                        wire:click="navigateUp"
                                        class="flex items-center gap-2 w-full text-left px-2 py-1.5 rounded text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                    >
                                        <flux:icon.arrow-uturn-left class="size-4 text-zinc-400" />
                                        <span class="text-zinc-500 dark:text-zinc-400">..</span>
                                    </button>
                                @endif

                                @forelse ($this->filteredFileTree as $entry)
                                    @php
                                        $isChanged = in_array($entry['path'], $this->changedFilePaths);
                                    @endphp
                                    <button
                                        wire:click="{{ $entry['type'] === 'directory' ? "navigateToDirectory('{$entry['path']}')" : "viewFile('{$entry['path']}')" }}"
                                        wire:key="file-{{ $loop->index }}"
                                        class="flex items-center gap-2 w-full text-left px-2 py-1.5 rounded text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                    >
                                        @if ($entry['type'] === 'directory')
                                            <flux:icon.folder class="size-4 text-zinc-400" />
                                        @else
                                            <flux:icon.document class="size-4 {{ $isChanged ? 'text-yellow-500 dark:text-yellow-400' : 'text-zinc-400' }}" />
                                        @endif
                                        <span class="font-mono text-xs truncate {{ $isChanged ? 'text-yellow-700 dark:text-yellow-300 font-medium' : 'text-zinc-700 dark:text-zinc-300' }}">
                                            {{ $entry['name'] }}
                                        </span>
                                    </button>
                                @empty
                                    <flux:text class="text-zinc-500 dark:text-zinc-400 text-sm py-2">
                                        {{ $fileSearch ? __('No files match your search.') : __('This directory is empty.') }}
                                    </flux:text>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
