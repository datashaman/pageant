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

new #[Title('Create Agent')] class extends Component {
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
        return Skill::query()->forCurrentOrganization()->orderBy('name')->get();
    }

    #[Computed]
    public function repos(): Collection
    {
        return Repo::query()->forCurrentOrganization()->orderBy('name')->get();
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

    public function save(): void
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

        $organizationId = auth()->user()->currentOrganizationId();
        abort_unless($organizationId, 403);

        $data = [
            'organization_id' => $organizationId,
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

        $agent = Agent::query()->create($data);

        $agent->skills()->sync($this->selectedSkills);
        $agent->repos()->sync($this->selectedRepos);

        $this->redirect(route('agents.show', $agent), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('agents.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Create Agent') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-2xl space-y-6">
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

            <div>
                <div class="flex items-center gap-2 mb-2">
                    <flux:heading size="sm">{{ __('Tools') }}</flux:heading>
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

            <div>
                <div class="flex items-center gap-2 mb-2">
                    <flux:heading size="sm">{{ __('Events') }}</flux:heading>
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

            @if ($this->skills->isNotEmpty())
                <flux:checkbox.group wire:model="selectedSkills" :label="__('Skills')">
                    @foreach ($this->skills as $skill)
                        <flux:checkbox :label="$skill->name" :value="$skill->id" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            @if ($this->repos->isNotEmpty())
                <flux:checkbox.group wire:model="selectedRepos" :label="__('Repos')">
                    @foreach ($this->repos as $repo)
                        <flux:checkbox :label="$repo->name" :value="$repo->id" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Create') }}
                </flux:button>
                <flux:button href="{{ route('agents.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
