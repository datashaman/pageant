<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Repo')] class extends Component {
    public string $organization_id = '';
    public string $name = '';
    public string $source = '';
    public string $source_reference = '';
    public string $source_url = '';

    public array $selectedSkills = [];
    public array $selectedAgents = [];
    public array $selectedProjects = [];

    #[Computed]
    public function organizations(): Collection
    {
        return auth()->user()->organizations;
    }

    #[Computed]
    public function availableSkills(): Collection
    {
        if (! $this->organization_id) {
            return new Collection;
        }

        return Skill::query()->where('organization_id', $this->organization_id)->get();
    }

    #[Computed]
    public function availableAgents(): Collection
    {
        if (! $this->organization_id) {
            return new Collection;
        }

        return Agent::query()->where('organization_id', $this->organization_id)->get();
    }

    #[Computed]
    public function availableProjects(): Collection
    {
        if (! $this->organization_id) {
            return new Collection;
        }

        return Project::query()->where('organization_id', $this->organization_id)->get();
    }

    public function updatedOrganizationId(): void
    {
        $this->selectedSkills = [];
        $this->selectedAgents = [];
        $this->selectedProjects = [];
    }

    public function save(): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        $validated = $this->validate([
            'organization_id' => ['required', 'uuid', 'in:' . $userOrgIds->implode(',')],
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'in:github,gitlab,bitbucket'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'selectedSkills' => ['array'],
            'selectedSkills.*' => ['uuid'],
            'selectedAgents' => ['array'],
            'selectedAgents.*' => ['uuid'],
            'selectedProjects' => ['array'],
            'selectedProjects.*' => ['uuid'],
        ]);

        $repo = Repo::query()->create([
            'organization_id' => $validated['organization_id'],
            'name' => $validated['name'],
            'source' => $validated['source'],
            'source_reference' => $validated['source_reference'],
            'source_url' => $validated['source_url'],
        ]);

        $repo->skills()->sync($this->selectedSkills);
        $repo->agents()->sync($this->selectedAgents);
        $repo->projects()->sync($this->selectedProjects);

        $this->redirect(route('repos.show', $repo), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('repos.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Create Repo') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-xl space-y-6">
            <flux:select wire:model.live="organization_id" :label="__('Organization')" required>
                <flux:select.option value="">{{ __('Select organization...') }}</flux:select.option>
                @foreach ($this->organizations as $organization)
                    <flux:select.option :value="$organization->id">{{ $organization->title }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

            <flux:select wire:model="source" :label="__('Source')" required>
                <flux:select.option value="">{{ __('Select source...') }}</flux:select.option>
                <flux:select.option value="github">GitHub</flux:select.option>
                <flux:select.option value="gitlab">GitLab</flux:select.option>
                <flux:select.option value="bitbucket">Bitbucket</flux:select.option>
            </flux:select>

            <flux:input wire:model="source_reference" :label="__('Source Reference')" type="text" />
            <flux:input wire:model="source_url" :label="__('Source URL')" type="url" />

            @if ($this->organization_id)
                @if ($this->availableSkills->isNotEmpty())
                    <flux:checkbox.group wire:model="selectedSkills" :label="__('Skills')">
                        @foreach ($this->availableSkills as $skill)
                            <flux:checkbox :label="$skill->name" :value="$skill->id" />
                        @endforeach
                    </flux:checkbox.group>
                @endif

                @if ($this->availableAgents->isNotEmpty())
                    <flux:checkbox.group wire:model="selectedAgents" :label="__('Agents')">
                        @foreach ($this->availableAgents as $agent)
                            <flux:checkbox :label="$agent->name" :value="$agent->id" />
                        @endforeach
                    </flux:checkbox.group>
                @endif

                @if ($this->availableProjects->isNotEmpty())
                    <flux:checkbox.group wire:model="selectedProjects" :label="__('Projects')">
                        @foreach ($this->availableProjects as $project)
                            <flux:checkbox :label="$project->name" :value="$project->id" />
                        @endforeach
                    </flux:checkbox.group>
                @endif
            @endif

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Create') }}
                </flux:button>
                <flux:button href="{{ route('repos.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
