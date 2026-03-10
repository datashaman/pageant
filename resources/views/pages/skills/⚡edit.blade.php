<?php

use App\Models\Agent;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Skill')] class extends Component {
    public Skill $skill;

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

    public function mount(Skill $skill): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($skill->organization_id), 403);

        $this->skill = $skill->load(['agents', 'repos']);

        $this->name = $skill->name;
        $this->description = $skill->description ?? '';
        $this->argumentHint = $skill->argument_hint ?? '';
        $this->license = $skill->license ?? '';
        $this->enabled = $skill->enabled;
        $this->path = $skill->path ?? '';
        $this->allowedToolsText = implode(', ', $skill->allowed_tools ?? []);
        $this->provider = $skill->provider ?? '';
        $this->model = $skill->model ?? '';
        $this->context = $skill->context ?? '';
        $this->agent_id = $skill->agent_id ?? '';
        $this->source = $skill->source ?? '';
        $this->sourceReference = $skill->source_reference ?? '';
        $this->sourceUrl = $skill->source_url ?? '';
        $this->selectedAgents = $skill->agents->pluck('id')->toArray();
        $this->selectedRepos = $skill->repos->pluck('id')->toArray();
    }

    #[Computed]
    public function agents(): Collection
    {
        return Agent::query()
            ->where('organization_id', $this->skill->organization_id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function repos(): Collection
    {
        return Repo::query()
            ->where('organization_id', $this->skill->organization_id)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->skill->organization_id), 403);

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
            'agent_id' => ['nullable', 'uuid', Rule::exists('agents', 'id')->where('organization_id', $this->skill->organization_id)],
            'source' => ['nullable', 'string', 'max:255'],
            'sourceReference' => ['nullable', 'string', 'max:255'],
            'sourceUrl' => ['nullable', 'string', 'url', 'max:255'],
            'selectedAgents' => ['array'],
            'selectedAgents.*' => ['uuid', Rule::exists('agents', 'id')->where('organization_id', $this->skill->organization_id)],
            'selectedRepos' => ['array'],
            'selectedRepos.*' => ['uuid', Rule::exists('repos', 'id')->where('organization_id', $this->skill->organization_id)],
        ]);

        $this->skill->update([
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

        $this->skill->agents()->sync($this->selectedAgents);
        $this->skill->repos()->sync($this->selectedRepos);

        $this->redirect(route('skills.show', $this->skill), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'skills.edit', 'skill_id' => $skill->id, 'skill_name' => $skill->name]) }}">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('skills.show', $skill) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Skill') }}</flux:heading>
        </div>

        <x-skills.form
            :agents="$this->agents"
            :repos="$this->repos"
            :submit-label="__('Update')"
            :cancel-url="route('skills.show', $skill)"
        />
    </div>
</div>
