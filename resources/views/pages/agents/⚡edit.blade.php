<?php

use App\Ai\EventRegistry;
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
    public array $selectedEvents = [];
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
        $this->selectedEvents = $agent->events ?? [];
        $this->provider = $agent->provider ?? '';
        $this->model = $agent->model ?? '';
        $this->permission_mode = $agent->permission_mode ?? '';
        $this->max_turns = $agent->max_turns;
        $this->background = $agent->background ?? false;
        $this->isolation = $agent->isolation ?? '';
        $this->selectedSkills = $agent->skills->pluck('id')->toArray();
        $this->selectedRepos = $agent->repos->pluck('id')->toArray();
    }

    #[Computed]
    public function groupedTools(): array
    {
        return ToolRegistry::grouped();
    }

    #[Computed]
    public function groupedEvents(): array
    {
        return EventRegistry::grouped();
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
        $this->selectedTools = array_keys(ToolRegistry::available());
    }

    public function deselectAllTools(): void
    {
        $this->selectedTools = [];
    }

    public function selectAllEvents(): void
    {
        $this->selectedEvents = array_keys(EventRegistry::available());
    }

    public function deselectAllEvents(): void
    {
        $this->selectedEvents = [];
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
            'events' => $this->selectedEvents,
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
                        <flux:checkbox.group wire:model="selectedEvents">
                            @foreach ($this->groupedEvents as $group => $events)
                                <div class="mb-4">
                                    <flux:heading size="xs" class="mb-2">{{ $group }}</flux:heading>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach ($events as $name => $description)
                                            <flux:checkbox :label="$description" :value="$name" />
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </flux:checkbox.group>
                    </div>
                </div>

                {{-- Tools tab --}}
                <div x-show="tab === 'tools'" x-cloak>
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <flux:button size="xs" wire:click="selectAllTools">{{ __('Check all') }}</flux:button>
                            <flux:button size="xs" wire:click="deselectAllTools">{{ __('Uncheck all') }}</flux:button>
                        </div>
                        <flux:checkbox.group wire:model="selectedTools">
                            @foreach ($this->groupedTools as $group => $tools)
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
