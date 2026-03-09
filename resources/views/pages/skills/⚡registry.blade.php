<?php

use App\Models\Skill;
use App\Services\SkillRegistryService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Browse Skill Registry')] class extends Component {
    public string $search = '';
    public array $results = [];
    public bool $hasSearched = false;
    public bool $isSearching = false;
    public string $importMessage = '';
    public string $importError = '';

    public function searchRegistry(): void
    {
        $this->validate([
            'search' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $this->isSearching = true;
        $this->importMessage = '';
        $this->importError = '';

        $service = app(SkillRegistryService::class);
        $this->results = $service->search($this->search, 10)->toArray();
        $this->hasSearched = true;
        $this->isSearching = false;
    }

    public function importSkill(int $index): void
    {
        $this->importMessage = '';
        $this->importError = '';

        if (! isset($this->results[$index])) {
            $this->importError = __('Invalid skill selection.');

            return;
        }

        $result = $this->results[$index];
        $organizationId = auth()->user()->currentOrganizationId();
        abort_unless($organizationId, 403);

        $name = str($result['name'])->afterLast('/')->slug()->toString();

        $existing = Skill::query()
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            $this->importError = __('A skill named ":name" already exists in this organization.', ['name' => $name]);

            return;
        }

        $skill = Skill::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'description' => $result['description'] ?? '',
            'enabled' => true,
            'source' => $result['registry'],
            'source_reference' => $result['source_reference'] ?? '',
            'source_url' => $result['source_url'] ?? '',
            'allowed_tools' => [],
            'provider' => 'anthropic',
            'model' => 'inherit',
            'context' => '',
            'argument_hint' => '',
            'license' => '',
            'path' => '',
        ]);

        $this->importMessage = __('Skill ":name" imported successfully.', ['name' => $skill->name]);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'skills.registry']) }}">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('skills.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Browse Skill Registry') }}</flux:heading>
        </div>

        <flux:text>{{ __('Search public registries (MCP Registry, Smithery) for skills and MCP servers to import into your organization.') }}</flux:text>

        <form wire:submit="searchRegistry" class="flex items-end gap-4">
            <div class="grow">
                <flux:input wire:model="search" :label="__('Search')" placeholder="{{ __('e.g. filesystem, database, slack...') }}" icon="magnifying-glass" />
            </div>
            <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="searchRegistry">{{ __('Search') }}</span>
                <span wire:loading wire:target="searchRegistry">{{ __('Searching...') }}</span>
            </flux:button>
        </form>

        @if ($importMessage)
            <flux:callout variant="success" icon="check-circle">
                {{ $importMessage }}
            </flux:callout>
        @endif

        @if ($importError)
            <flux:callout variant="danger" icon="x-circle">
                {{ $importError }}
            </flux:callout>
        @endif

        @if ($hasSearched)
            @if (empty($results))
                <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-600">
                    <flux:icon.magnifying-glass class="size-10 text-zinc-400 dark:text-zinc-500" />
                    <flux:heading size="lg" class="mt-4">{{ __('No results found') }}</flux:heading>
                    <flux:text class="mt-1 max-w-sm">{{ __('Try a different search term to find skills in the public registries.') }}</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column>{{ __('Registry') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($results as $index => $result)
                            <flux:table.row wire:key="registry-result-{{ $index }}">
                                <flux:table.cell>
                                    @if ($result['source_url'])
                                        <flux:link href="{{ $result['source_url'] }}" target="_blank">
                                            {{ $result['name'] }}
                                        </flux:link>
                                    @else
                                        {{ $result['name'] }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-w-md">
                                    <span class="line-clamp-2">{{ Str::limit($result['description'], 150) }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" variant="outline">
                                        {{ $result['registry'] === 'mcp-registry' ? __('MCP Registry') : __('Smithery') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="primary" wire:click="importSkill({{ $index }})">
                                        {{ __('Import') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        @elseif (! $hasSearched)
            <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-600">
                <flux:icon.globe-alt class="size-10 text-zinc-400 dark:text-zinc-500" />
                <flux:heading size="lg" class="mt-4">{{ __('Search public registries') }}</flux:heading>
                <flux:text class="mt-1 max-w-sm">{{ __('Enter a search term above to find skills and MCP servers from the official MCP Registry and Smithery.') }}</flux:text>
            </div>
        @endif
    </div>
</div>
