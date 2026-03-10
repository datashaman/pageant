<?php

use App\Ai\EventRegistry;
use App\Ai\EventSubscription;
use App\Ai\ToolRegistry;
use App\Models\Agent;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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

    /** @return array<string, bool> */
    #[Computed]
    public function availableProviders(): array
    {
        $user = auth()->user();
        $userKeyProviders = $user->apiKeys()->valid()->pluck('provider')->toArray();

        $providers = [];
        foreach (['anthropic', 'openai', 'gemini'] as $provider) {
            $providers[$provider] = ! empty(config("ai.providers.{$provider}.key"))
                || in_array($provider, $userKeyProviders);
        }

        return $providers;
    }

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
        return Skill::query()->forUser()->orderBy('name')->get();
    }

    #[Computed]
    public function repos(): Collection
    {
        return Repo::query()->forUser()->orderBy('name')->get();
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
            'selectedSkills' => ['array'],
            'selectedSkills.*' => ['uuid', Rule::exists('skills', 'id')->where('organization_id', $this->agent->organization_id)],
            'selectedRepos' => ['array'],
            'selectedRepos.*' => ['uuid', Rule::exists('repos', 'id')->where('organization_id', $this->agent->organization_id)],
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

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'agents.edit', 'agent_id' => $agent->id, 'agent_name' => $agent->name]) }}">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('agents.show', $agent) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Agent') }}</flux:heading>
        </div>

        <x-agents.form
            :available-providers="$this->availableProviders"
            :available-models="$this->availableModels"
            :event-categories="$this->eventCategories"
            :tool-categories="$this->toolCategories"
            :skills="$this->skills"
            :repos="$this->repos"
            :selected-event-keys="$this->selectedEventKeys"
            :submit-label="__('Update')"
            :cancel-url="route('agents.show', $agent)"
            submit-action="update"
        />
    </div>
</div>
