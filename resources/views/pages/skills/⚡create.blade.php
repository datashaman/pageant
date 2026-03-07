<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Skill')] class extends Component {
    public string $organization_id = '';
    public string $name = '';
    public string $description = '';
    public string $argumentHint = '';
    public string $license = '';
    public bool $enabled = true;
    public string $path = '';
    public string $allowedToolsText = '';
    public string $provider = '';
    public string $model = '';
    public string $context = '';
    public string $agent_id = '';
    public string $source = '';
    public string $sourceReference = '';
    public string $sourceUrl = '';
    public array $selectedAgents = [];
    public array $selectedRepos = [];

    #[Computed]
    public function organizations(): Collection
    {
        return auth()->user()->organizations;
    }

    #[Computed]
    public function agents(): Collection
    {
        if (! $this->organization_id) {
            return collect();
        }

        return Agent::query()
            ->where('organization_id', $this->organization_id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function repos(): Collection
    {
        if (! $this->organization_id) {
            return collect();
        }

        return Repo::query()
            ->where('organization_id', $this->organization_id)
            ->orderBy('name')
            ->get();
    }

    public function updatedOrganizationId(): void
    {
        $this->agent_id = '';
        $this->selectedAgents = [];
        $this->selectedRepos = [];
    }

    public function save(): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->organization_id), 403);

        $validated = $this->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'argumentHint' => ['nullable', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'enabled' => ['boolean'],
            'path' => ['nullable', 'string', 'max:255'],
            'allowedToolsText' => ['nullable', 'string'],
            'provider' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'string'],
            'agent_id' => ['nullable', 'uuid', 'exists:agents,id'],
            'source' => ['nullable', 'string', 'max:255'],
            'sourceReference' => ['nullable', 'string', 'max:255'],
            'sourceUrl' => ['nullable', 'string', 'url', 'max:255'],
            'selectedAgents' => ['array'],
            'selectedAgents.*' => ['uuid', 'exists:agents,id'],
            'selectedRepos' => ['array'],
            'selectedRepos.*' => ['uuid', 'exists:repos,id'],
        ]);

        $skill = Skill::query()->create([
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'argument_hint' => $this->argumentHint,
            'license' => $this->license,
            'enabled' => $this->enabled,
            'path' => $this->path,
            'allowed_tools' => array_filter(array_map('trim', explode(',', $this->allowedToolsText))),
            'provider' => $this->provider,
            'model' => $this->model,
            'context' => $this->context,
            'agent_id' => $this->agent_id ?: null,
            'source' => $this->source,
            'source_reference' => $this->sourceReference,
            'source_url' => $this->sourceUrl,
        ]);

        $skill->agents()->sync($this->selectedAgents);
        $skill->repos()->sync($this->selectedRepos);

        $this->redirect(route('skills.show', $skill), navigate: true);
    }
}; ?>

<div>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('skills.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Create Skill') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-xl space-y-6">
            <flux:select wire:model.live="organization_id" :label="__('Organization')" required>
                <option value="">{{ __('Select Organization') }}</option>
                @foreach ($this->organizations as $organization)
                    <option value="{{ $organization->id }}">{{ $organization->title }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            <flux:input wire:model="argumentHint" :label="__('Argument Hint')" type="text" />
            <flux:input wire:model="license" :label="__('License')" type="text" />
            <flux:checkbox wire:model="enabled" :label="__('Enabled')" />
            <flux:input wire:model="path" :label="__('Path')" type="text" />

            <flux:textarea wire:model="allowedToolsText" :label="__('Allowed Tools')" :description="__('Comma-separated list of tool names')" rows="2" />

            <flux:input wire:model="provider" :label="__('Provider')" type="text" />
            <flux:input wire:model="model" :label="__('Model')" type="text" />
            <flux:textarea wire:model="context" :label="__('Context')" rows="3" />

            <flux:select wire:model="agent_id" :label="__('Primary Agent')">
                <option value="">{{ __('None') }}</option>
                @foreach ($this->agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model="source" :label="__('Source')">
                <option value="">{{ __('None') }}</option>
                <option value="github">{{ __('GitHub') }}</option>
                <option value="gitlab">{{ __('GitLab') }}</option>
                <option value="bitbucket">{{ __('Bitbucket') }}</option>
            </flux:select>

            <flux:input wire:model="sourceReference" :label="__('Source Reference')" type="text" />
            <flux:input wire:model="sourceUrl" :label="__('Source URL')" type="text" />

            @if ($this->agents->isNotEmpty())
                <fieldset class="space-y-2">
                    <flux:heading size="sm">{{ __('Associated Agents') }}</flux:heading>
                    @foreach ($this->agents as $agent)
                        <flux:checkbox wire:model="selectedAgents" :label="$agent->name" :value="$agent->id" />
                    @endforeach
                </fieldset>
            @endif

            @if ($this->repos->isNotEmpty())
                <fieldset class="space-y-2">
                    <flux:heading size="sm">{{ __('Associated Repos') }}</flux:heading>
                    @foreach ($this->repos as $repo)
                        <flux:checkbox wire:model="selectedRepos" :label="$repo->name" :value="$repo->id" />
                    @endforeach
                </fieldset>
            @endif

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Create') }}
                </flux:button>
                <flux:button href="{{ route('skills.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
