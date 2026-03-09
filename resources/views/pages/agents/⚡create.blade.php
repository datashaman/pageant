<?php

use App\Ai\EventRegistry;
use App\Ai\ToolRegistry;
use App\Models\Agent;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Agent')] class extends Component {
    public string $name = '';
    public ?string $description = '';
    public array $selectedTools = [];
    public array $selectedEventKeys = [];
    public array $eventFilters = [];
    public ?string $provider = '';
    public ?string $model = '';
    public ?string $permission_mode = '';
    public ?int $max_turns = null;
    public bool $background = false;
    public ?string $isolation = '';
    public array $selectedSkills = [];
    public array $selectedRepos = [];

    /** @return array<string, string> */
    #[Computed]
    public function availableModels(): array
    {
        return match ($this->provider) {
            'anthropic' => [
                'claude-opus-4-6' => 'Claude Opus 4.6',
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
            ],
            'openai' => [
                'gpt-4.1' => 'GPT-4.1',
                'gpt-4.1-mini' => 'GPT-4.1 Mini',
                'gpt-4.1-nano' => 'GPT-4.1 Nano',
                'o3' => 'o3',
                'o4-mini' => 'o4-mini',
            ],
            'gemini' => [
                'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            ],
            default => [],
        };
    }

    #[Computed]
    public function toolCategories(): array
    {
        return ToolRegistry::groupedByCategory();
    }

    #[Computed]
    public function eventCategories(): array
    {
        $categories = [];

        foreach (EventRegistry::groupedByCategory() as $category => $groups) {
            $sections = [];

            foreach ($groups as $group => $eventTypes) {
                foreach ($eventTypes as $eventName => $details) {
                    $label = $details['label'];
                    if (! isset($sections[$label])) {
                        $sections[$label] = ['label' => $label, 'checkboxes' => [], 'filters' => [], 'eventNames' => []];
                    }

                    $sections[$label]['eventNames'][] = $eventName;

                    if (empty($details['actions'])) {
                        $sections[$label]['checkboxes'][] = ['label' => $details['description'], 'value' => $eventName];
                    } else {
                        foreach ($details['actions'] as $action) {
                            $sections[$label]['checkboxes'][] = [
                                'label' => ucfirst(str_replace('_', ' ', $action)),
                                'value' => "{$eventName}.{$action}",
                            ];
                        }
                    }

                    foreach ($details['filters'] as $filter) {
                        $sections[$label]['filters'][$eventName][] = $filter;
                    }
                }
            }

            $categories[$category] = array_values($sections);
        }

        return $categories;
    }

    #[Computed]
    public function skills(): Collection
    {
        return Skill::query()->forCurrentOrganization()->orderBy('name')->get();
    }

    #[Computed]
    public function repos(): Collection
    {
        return Repo::query()->forCurrentOrganization()->orderBy('name')->get();
    }

    public function selectToolsByCategory(string $category): void
    {
        $names = match ($category) {
            'github' => ToolRegistry::githubToolNames(),
            'pageant' => ToolRegistry::pageantToolNames(),
            'worktree' => ToolRegistry::worktreeToolNames(),
            default => [],
        };
        $this->selectedTools = array_values(array_unique(array_merge($this->selectedTools, $names)));
    }

    public function deselectToolsByCategory(string $category): void
    {
        $names = match ($category) {
            'github' => ToolRegistry::githubToolNames(),
            'pageant' => ToolRegistry::pageantToolNames(),
            'worktree' => ToolRegistry::worktreeToolNames(),
            default => [],
        };
        $this->selectedTools = array_values(array_diff($this->selectedTools, $names));
    }

    public function selectAllEvents(): void
    {
        $this->selectedEventKeys = EventRegistry::allEventKeys();
    }

    public function deselectAllEvents(): void
    {
        $this->selectedEventKeys = [];
        $this->eventFilters = [];
    }

    protected function buildEventSubscriptions(): array
    {
        $subscriptionsByType = [];

        foreach ($this->selectedEventKeys as $key) {
            if (str_contains($key, '.')) {
                [$type, $action] = explode('.', $key, 2);
            } else {
                $type = $key;
                $action = null;
            }

            if (! isset($subscriptionsByType[$type])) {
                $subscriptionsByType[$type] = [];
            }

            $subscriptionsByType[$type][] = $action;
        }

        $subscriptions = [];

        foreach ($subscriptionsByType as $type => $actions) {
            $allActions = EventRegistry::actionsFor($type);
            $nonNullActions = array_filter($actions, fn ($a) => $a !== null);
            $hasBaseType = in_array(null, $actions, true);

            if ($hasBaseType || (count($nonNullActions) === count($allActions) && count($allActions) > 0)) {
                $event = $type;
            } else {
                foreach ($nonNullActions as $action) {
                    $event = "{$type}.{$action}";
                    $filters = $this->getFiltersForType($type);
                    $subscriptions[] = ['event' => $event, 'filters' => $filters];
                }

                continue;
            }

            $filters = $this->getFiltersForType($type);
            $subscriptions[] = ['event' => $event, 'filters' => $filters];
        }

        return $subscriptions;
    }

    protected function getFiltersForType(string $type): array
    {
        $filters = [];
        $raw = $this->eventFilters[$type] ?? [];

        if (! empty($raw['labels'])) {
            $filters['labels'] = array_map('trim', explode(',', $raw['labels']));
            $filters['labels'] = array_values(array_filter($filters['labels']));
        }

        if (! empty($raw['base_branch'])) {
            $filters['base_branch'] = trim($raw['base_branch']);
        }

        if (! empty($raw['branches'])) {
            $filters['branches'] = array_map('trim', explode(',', $raw['branches']));
            $filters['branches'] = array_values(array_filter($filters['branches']));
        }

        return $filters;
    }

    public function save(): void
    {
        $organizationId = auth()->user()->currentOrganizationId();
        abort_unless($organizationId, 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'permission_mode' => ['nullable', 'string'],
            'max_turns' => ['nullable', 'integer', 'min:1'],
            'isolation' => ['nullable', 'string'],
            'selectedSkills' => ['array'],
            'selectedSkills.*' => ['uuid', Rule::exists('skills', 'id')->where('organization_id', $organizationId)],
            'selectedRepos' => ['array'],
            'selectedRepos.*' => ['uuid', Rule::exists('repos', 'id')->where('organization_id', $organizationId)],
        ]);

        $data = [
            'organization_id' => $organizationId,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'tools' => $this->selectedTools,
            'events' => $this->buildEventSubscriptions(),
            'provider' => $this->provider ?: null,
            'model' => $this->model ?: null,
            'permission_mode' => $this->permission_mode ?: null,
            'max_turns' => $this->max_turns,
            'background' => $this->background,
        ];

        if (! empty($this->isolation)) {
            $data['isolation'] = $this->isolation;
        }

        $agent = Agent::query()->create($data);

        $agent->skills()->sync($this->selectedSkills);
        $agent->repos()->sync($this->selectedRepos);

        $this->redirect(route('agents.show', $agent), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'agents.create']) }}">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('agents.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Create Agent') }}</flux:heading>
        </div>

        <form wire:submit="save" x-data="{ tab: 'general', eventsSubtab: 'github', toolsSubtab: 'github' }">
            <div class="mb-6 flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
                <button type="button" @click="tab = 'general'"
                    :class="tab === 'general' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-2 text-sm font-medium transition">
                    {{ __('General') }}
                </button>
                <button type="button" @click="tab = 'events'"
                    :class="tab === 'events' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-2 text-sm font-medium transition">
                    {{ __('Events') }}
                </button>
                <button type="button" @click="tab = 'tools'"
                    :class="tab === 'tools' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-2 text-sm font-medium transition">
                    {{ __('Tools') }}
                </button>
                <button type="button" @click="tab = 'skills'"
                    :class="tab === 'skills' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-2 text-sm font-medium transition">
                    {{ __('Skills') }}
                </button>
                <button type="button" @click="tab = 'repos'"
                    :class="tab === 'repos' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-2 text-sm font-medium transition">
                    {{ __('Repos') }}
                </button>
            </div>

            <div class="space-y-6">
                {{-- General tab --}}
                <div x-show="tab === 'general'" x-cloak>
                    <div class="max-w-2xl space-y-6">
                        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

                        <flux:textarea wire:model="description" :label="__('Description')" />

                        <flux:select wire:model="provider" :label="__('Provider')">
                            <option value="">{{ __('Select Provider') }}</option>
                            <option value="anthropic">{{ __('Anthropic') }}</option>
                            <option value="openai">{{ __('OpenAI') }}</option>
                            <option value="gemini">{{ __('Gemini') }}</option>
                        </flux:select>

                        <flux:select wire:model="model" :label="__('Model')">
                            <option value="inherit">{{ __('Default') }}</option>
                            <optgroup label="{{ __('Strategy') }}">
                                <option value="cheapest">{{ __('Cheapest Model') }}</option>
                                <option value="smartest">{{ __('Smartest Model') }}</option>
                            </optgroup>
                            @if ($this->availableModels)
                                <optgroup label="{{ __('Models') }}">
                                    @foreach ($this->availableModels as $modelId => $modelLabel)
                                        <option value="{{ $modelId }}">{{ $modelLabel }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </flux:select>

                        <flux:select wire:model="permission_mode" :label="__('Permission Mode')">
                            <option value="">{{ __('Select Permission Mode') }}</option>
                            <option value="full">{{ __('Full') }}</option>
                            <option value="limited">{{ __('Limited') }}</option>
                        </flux:select>

                        <flux:input wire:model="max_turns" :label="__('Max Turns')" type="number" min="1" />

                        <flux:checkbox wire:model="background" :label="__('Background')" />

                        <flux:select wire:model="isolation" :label="__('Isolation')">
                            <option value="">{{ __('None') }}</option>
                            <option value="worktree">{{ __('Worktree') }}</option>
                        </flux:select>
                    </div>
                </div>

                {{-- Events tab --}}
                <div x-show="tab === 'events'" x-cloak>
                    <div class="flex gap-6">
                        {{-- Vertical sub-tabs --}}
                        <div class="flex w-36 shrink-0 flex-col gap-1 border-r border-zinc-200 pr-6 dark:border-zinc-700">
                            @foreach (['github' => 'GitHub', 'pageant' => 'Pageant'] as $key => $label)
                                <button type="button" @click="eventsSubtab = '{{ $key }}'"
                                    :class="eventsSubtab === '{{ $key }}' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                                    class="rounded-md px-3 py-2 text-left text-sm font-medium transition">
                                    {{ __($label) }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Event content panel --}}
                        <div class="min-w-0 grow">
                            <div class="mb-4 flex items-center gap-2">
                                <flux:button size="xs" wire:click="selectAllEvents">{{ __('Check all') }}</flux:button>
                                <flux:button size="xs" wire:click="deselectAllEvents">{{ __('Uncheck all') }}</flux:button>
                            </div>
                            <flux:checkbox.group wire:model="selectedEventKeys">
                                @foreach ($this->eventCategories as $category => $sections)
                                    <div x-show="eventsSubtab === '{{ $category }}'" x-cloak>
                                        @foreach ($sections as $section)
                                            <div class="mb-4">
                                                <flux:heading size="xs" class="mb-2">{{ $section['label'] }}</flux:heading>
                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    @foreach ($section['checkboxes'] as $checkbox)
                                                        <flux:checkbox :label="$checkbox['label']" :value="$checkbox['value']" />
                                                    @endforeach
                                                </div>

                                                @foreach ($section['filters'] as $eventName => $filterTypes)
                                                    @php
                                                        $hasSelectedAction = collect($this->selectedEventKeys)->contains(fn ($k) => $k === $eventName || str_starts_with($k, $eventName . '.'));
                                                    @endphp
                                                    @if ($hasSelectedAction)
                                                        <div class="mt-3 space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Filters (optional)') }}</flux:text>
                                                            @if (in_array('labels', $filterTypes))
                                                                <flux:input wire:model="eventFilters.{{ $eventName }}.labels" :label="__('Labels')" placeholder="bug, enhancement" size="sm" />
                                                            @endif
                                                            @if (in_array('base_branch', $filterTypes))
                                                                <flux:input wire:model="eventFilters.{{ $eventName }}.base_branch" :label="__('Base Branch')" placeholder="main" size="sm" />
                                                            @endif
                                                            @if (in_array('branches', $filterTypes))
                                                                <flux:input wire:model="eventFilters.{{ $eventName }}.branches" :label="__('Branches')" placeholder="main, release/*" size="sm" />
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </flux:checkbox.group>
                        </div>
                    </div>
                </div>

                {{-- Tools tab --}}
                <div x-show="tab === 'tools'" x-cloak>
                    <div class="flex gap-6">
                        {{-- Vertical sub-tabs --}}
                        <div class="flex w-36 shrink-0 flex-col gap-1 border-r border-zinc-200 pr-6 dark:border-zinc-700">
                            @foreach (['github' => 'GitHub', 'pageant' => 'Pageant', 'worktree' => 'Worktree'] as $key => $label)
                                <button type="button" @click="toolsSubtab = '{{ $key }}'"
                                    :class="toolsSubtab === '{{ $key }}' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                                    class="rounded-md px-3 py-2 text-left text-sm font-medium transition">
                                    {{ __($label) }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Tools content panel --}}
                        <div class="min-w-0 grow">
                            @foreach ($this->toolCategories as $category => $groups)
                                <div x-show="toolsSubtab === '{{ $category }}'" x-cloak>
                                    <div class="mb-4 flex items-center gap-2">
                                        <flux:button size="xs" wire:click="selectToolsByCategory('{{ $category }}')">{{ __('Check all') }}</flux:button>
                                        <flux:button size="xs" wire:click="deselectToolsByCategory('{{ $category }}')">{{ __('Uncheck all') }}</flux:button>
                                    </div>
                                    <flux:checkbox.group wire:model="selectedTools">
                                        @foreach ($groups as $group => $tools)
                                            <div class="mb-4">
                                                <flux:heading size="xs" class="mb-2">{{ $group }}</flux:heading>
                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    @foreach ($tools as $name => $description)
                                                        <flux:checkbox :label="$description" :value="$name" />
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </flux:checkbox.group>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Skills tab --}}
                <div x-show="tab === 'skills'" x-cloak>
                    <div class="max-w-2xl space-y-4">
                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                            {{ __('Selected skills are injected into the agent\'s context at startup. Agents do not inherit skills from the parent conversation.') }}
                        </flux:text>
                        @if ($this->skills->isNotEmpty())
                            <flux:checkbox.group wire:model="selectedSkills">
                                @foreach ($this->skills as $skill)
                                    <div>
                                        <flux:checkbox :label="$skill->name" :value="$skill->id" />
                                        @if ($skill->description)
                                            <flux:text class="ml-7 text-xs text-zinc-500 dark:text-zinc-400">{{ $skill->description }}</flux:text>
                                        @endif
                                    </div>
                                @endforeach
                            </flux:checkbox.group>
                        @else
                            <flux:text class="text-zinc-500 dark:text-zinc-400">
                                {{ __('No skills available for this organization.') }}
                            </flux:text>
                        @endif
                    </div>
                </div>

                {{-- Repos tab --}}
                <div x-show="tab === 'repos'" x-cloak>
                    <div class="max-w-2xl space-y-4">
                        @if ($this->repos->isNotEmpty())
                            <flux:checkbox.group wire:model="selectedRepos">
                                @foreach ($this->repos as $repo)
                                    <flux:checkbox :label="$repo->name" :value="$repo->id" />
                                @endforeach
                            </flux:checkbox.group>
                        @else
                            <flux:text class="text-zinc-500 dark:text-zinc-400">
                                {{ __('No repos available for this organization.') }}
                            </flux:text>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">
                        {{ __('Create') }}
                    </flux:button>
                    <flux:button href="{{ route('agents.index') }}" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</div>
