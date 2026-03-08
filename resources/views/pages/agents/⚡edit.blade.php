<?php

use App\Ai\EventRegistry;
use App\Ai\EventSubscription;
use App\Ai\ToolRegistry;
use App\Models\Agent;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Agent')] class extends Component {
    public Agent $agent;

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

    public function mount(Agent $agent): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($agent->organization_id), 403);

        $this->agent = $agent;
        $this->name = $agent->name;
        $this->description = $agent->description ?? '';
        $this->selectedTools = $agent->tools ?? [];
        $this->hydrateEventSubscriptions($agent->events ?? []);
        $this->provider = $agent->provider ?? '';
        $this->model = $agent->model ?? '';
        $this->permission_mode = $agent->permission_mode ?? '';
        $this->max_turns = $agent->max_turns;
        $this->background = $agent->background ?? false;
        $this->isolation = $agent->isolation ?? '';
        $this->selectedSkills = $agent->skills->pluck('id')->toArray();
        $this->selectedRepos = $agent->repos->pluck('id')->toArray();
    }

    protected function hydrateEventSubscriptions(array $events): void
    {
        $this->selectedEventKeys = [];
        $this->eventFilters = [];

        foreach ($events as $entry) {
            if (is_string($entry)) {
                $entry = ['event' => $entry, 'filters' => []];
            }

            $sub = EventSubscription::fromArray($entry);
            $eventType = $sub->eventType;

            if ($sub->action) {
                $this->selectedEventKeys[] = "{$eventType}.{$sub->action}";
            } else {
                $actions = EventRegistry::actionsFor($eventType);

                if (empty($actions)) {
                    $this->selectedEventKeys[] = $eventType;
                } else {
                    foreach ($actions as $action) {
                        $this->selectedEventKeys[] = "{$eventType}.{$action}";
                    }
                }
            }

            if (! empty($sub->filters)) {
                $existing = $this->eventFilters[$eventType] ?? [];

                if (! empty($sub->filters['labels'])) {
                    $existing['labels'] = implode(', ', $sub->filters['labels']);
                }

                if (! empty($sub->filters['base_branch'])) {
                    $existing['base_branch'] = $sub->filters['base_branch'];
                }

                if (! empty($sub->filters['branches'])) {
                    $existing['branches'] = implode(', ', $sub->filters['branches']);
                }

                $this->eventFilters[$eventType] = $existing;
            }
        }

        $this->selectedEventKeys = array_values(array_unique($this->selectedEventKeys));
    }

    #[Computed]
    public function toolCategories(): array
    {
        return ToolRegistry::groupedByCategory();
    }

    /**
     * @return array<int, array{label: string, checkboxes: array<int, array{label: string, value: string}>, filters: array<string, string>}>
     */
    #[Computed]
    public function eventSections(): array
    {
        $sections = [];

        foreach (EventRegistry::groupedWithDetails() as $group => $eventTypes) {
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

        return array_values($sections);
    }

    #[Computed]
    public function skills(): Collection
    {
        return Skill::query()->forUser()->orderBy('name')->get();
    }

    #[Computed]
    public function repos(): Collection
    {
        return Repo::query()->forUser()->orderBy('name')->get();
    }

    public function selectAllTools(): void
    {
        $this->selectedTools = array_values(array_unique(array_merge($this->selectedTools, ToolRegistry::githubToolNames())));
    }

    public function deselectAllTools(): void
    {
        $this->selectedTools = array_values(array_diff($this->selectedTools, ToolRegistry::githubToolNames()));
    }

    public function selectAllPageantTools(): void
    {
        $this->selectedTools = array_values(array_unique(array_merge($this->selectedTools, ToolRegistry::pageantToolNames())));
    }

    public function deselectAllPageantTools(): void
    {
        $this->selectedTools = array_values(array_diff($this->selectedTools, ToolRegistry::pageantToolNames()));
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

    public function update(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'permission_mode' => ['nullable', 'string'],
            'max_turns' => ['nullable', 'integer', 'min:1'],
            'isolation' => ['nullable', 'string'],
        ]);

        $data = [
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

        $this->agent->update($data);

        $this->agent->skills()->sync($this->selectedSkills);
        $this->agent->repos()->sync($this->selectedRepos);

        $this->redirect(route('agents.show', $this->agent), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('agents.show', $agent) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Agent') }}</flux:heading>
        </div>

        <form wire:submit="update" x-data="{ tab: 'general' }">
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
                <button type="button" @click="tab = 'github-tools'"
                    :class="tab === 'github-tools' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-2 text-sm font-medium transition">
                    {{ __('GitHub Tools') }}
                </button>
                <button type="button" @click="tab = 'pageant-tools'"
                    :class="tab === 'pageant-tools' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-2 text-sm font-medium transition">
                    {{ __('Pageant Tools') }}
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

            <div class="max-w-2xl space-y-6">
                {{-- General tab --}}
                <div x-show="tab === 'general'" x-cloak>
                    <div class="space-y-6">
                        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

                        <flux:textarea wire:model="description" :label="__('Description')" />

                        <flux:select wire:model="provider" :label="__('Provider')">
                            <option value="">{{ __('Select Provider') }}</option>
                            <option value="anthropic">{{ __('Anthropic') }}</option>
                            <option value="openai">{{ __('OpenAI') }}</option>
                        </flux:select>

                        <flux:input wire:model="model" :label="__('Model')" type="text" />

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
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <flux:button size="xs" wire:click="selectAllEvents">{{ __('Check all') }}</flux:button>
                            <flux:button size="xs" wire:click="deselectAllEvents">{{ __('Uncheck all') }}</flux:button>
                        </div>
                        <flux:checkbox.group wire:model="selectedEventKeys">
                            @foreach ($this->eventSections as $section)
                                <div class="mb-4">
                                    <flux:heading size="xs" class="mb-2">{{ $section['label'] }}</flux:heading>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach ($section['checkboxes'] as $checkbox)
                                            <flux:checkbox :label="$checkbox['label']" :value="$checkbox['value']" :description="$checkbox['value']" />
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
                        </flux:checkbox.group>
                    </div>
                </div>

                {{-- GitHub Tools tab --}}
                <div x-show="tab === 'github-tools'" x-cloak>
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <flux:button size="xs" wire:click="selectAllTools">{{ __('Check all') }}</flux:button>
                            <flux:button size="xs" wire:click="deselectAllTools">{{ __('Uncheck all') }}</flux:button>
                        </div>
                        <flux:checkbox.group wire:model="selectedTools">
                            @foreach ($this->toolCategories['github'] as $group => $tools)
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
                </div>

                {{-- Pageant Tools tab --}}
                <div x-show="tab === 'pageant-tools'" x-cloak>
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <flux:button size="xs" wire:click="selectAllPageantTools">{{ __('Check all') }}</flux:button>
                            <flux:button size="xs" wire:click="deselectAllPageantTools">{{ __('Uncheck all') }}</flux:button>
                        </div>
                        <flux:checkbox.group wire:model="selectedTools">
                            @foreach ($this->toolCategories['pageant'] as $group => $tools)
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
                </div>

                {{-- Skills tab --}}
                <div x-show="tab === 'skills'" x-cloak>
                    <div class="space-y-4">
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
                    <div class="space-y-4">
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
                        {{ __('Update') }}
                    </flux:button>
                    <flux:button href="{{ route('agents.show', $agent) }}" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</div>
