<?php

use App\Models\Agent;
use App\Models\Skill;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Skill')] class extends Component {
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
    public array $selectedWorkspaces = [];

    #[Computed]
    public function agents(): Collection
    {
        return Agent::query()->forCurrentOrganization()->orderBy('name')->get();
    }

    #[Computed]
    public function workspaces(): Collection
    {
        return Workspace::query()->forCurrentOrganization()->orderBy('name')->get();
    }

    public function save(): void
    {
        $organizationId = auth()->user()->currentOrganizationId();
        abort_unless($organizationId, 403);

        $this->validate([
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
            'agent_id' => ['nullable', 'uuid', Rule::exists('agents', 'id')->where('organization_id', $organizationId)],
            'source' => ['nullable', 'string', 'max:255'],
            'sourceReference' => ['nullable', 'string', 'max:255'],
            'sourceUrl' => ['nullable', 'string', 'url', 'max:255'],
            'selectedAgents' => ['array'],
            'selectedAgents.*' => ['uuid', Rule::exists('agents', 'id')->where('organization_id', $organizationId)],
            'selectedWorkspaces' => ['array'],
            'selectedWorkspaces.*' => ['uuid', Rule::exists('workspaces', 'id')->where('organization_id', $organizationId)],
        ]);

        $skill = Skill::query()->create([
            'organization_id' => $organizationId,
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
        $skill->workspaces()->sync($this->selectedWorkspaces);

        $this->redirect(route('skills.show', $skill), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'skills.create']) }}">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('skills.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Create Skill') }}</flux:heading>
        </div>

        <x-skills.form
            :agents="$this->agents"
            :workspaces="$this->workspaces"
            :submit-label="__('Create')"
            :cancel-url="route('skills.index')"
        />
    </div>
</div>
