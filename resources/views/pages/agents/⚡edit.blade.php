<?php

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

    public string $organization_id = '';
    public string $name = '';
    public ?string $description = '';
    public array $selectedTools = [];
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
        $this->organization_id = $agent->organization_id;
        $this->name = $agent->name;
        $this->description = $agent->description ?? '';
        $this->selectedTools = $agent->tools ?? [];
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
    public function availableTools(): array
    {
        return array_keys(ToolRegistry::available());
    }

    #[Computed]
    public function organizations(): Collection
    {
        return auth()->user()->organizations;
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
        $this->selectedTools = $this->availableTools;
    }

    public function deselectAllTools(): void
    {
        $this->selectedTools = [];
    }

    public function update(): void
    {
        $this->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'permission_mode' => ['nullable', 'string'],
            'max_turns' => ['nullable', 'integer', 'min:1'],
            'isolation' => ['nullable', 'string'],
        ]);

        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->organization_id), 403);

        $this->agent->update([
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'tools' => $this->selectedTools,
            'provider' => $this->provider ?: null,
            'model' => $this->model ?: null,
            'permission_mode' => $this->permission_mode ?: null,
            'max_turns' => $this->max_turns,
            'background' => $this->background,
            'isolation' => $this->isolation ?: null,
        ]);

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

        <form wire:submit="update" class="max-w-xl space-y-6">
            <flux:select wire:model="organization_id" :label="__('Organization')" required>
                <option value="">{{ __('Select Organization') }}</option>
                @foreach ($this->organizations as $organization)
                    <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                @endforeach
            </flux:select>

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
                <option value="">{{ __('Select Isolation') }}</option>
                <option value="false">{{ __('False') }}</option>
                <option value="true">{{ __('True') }}</option>
            </flux:select>

            <div>
                <div class="flex items-center gap-2 mb-2">
                    <flux:heading size="sm">{{ __('Tools') }}</flux:heading>
                    <flux:button size="xs" wire:click="selectAllTools">{{ __('Check all') }}</flux:button>
                    <flux:button size="xs" wire:click="deselectAllTools">{{ __('Uncheck all') }}</flux:button>
                </div>
                <flux:checkbox.group wire:model="selectedTools">
                    @foreach ($this->availableTools as $tool)
                        <flux:checkbox :label="$tool" :value="$tool" />
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
                    {{ __('Update') }}
                </flux:button>
                <flux:button href="{{ route('agents.show', $agent) }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
