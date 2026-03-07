<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Repo')] class extends Component {
    public Repo $repo;

    public string $organization_id = '';
    public string $name = '';
    public string $source = '';
    public string $source_reference = '';
    public string $source_url = '';

    public array $selectedSkills = [];
    public array $selectedAgents = [];
    public array $selectedProjects = [];

    public function mount(Repo $repo): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->repo->organization_id), 403);

        $this->organization_id = $repo->organization_id;
        $this->name = $repo->name;
        $this->source = $repo->source;
        $this->source_reference = $repo->source_reference ?? '';
        $this->source_url = $repo->source_url ?? '';

        $this->selectedSkills = $repo->skills->pluck('id')->toArray();
        $this->selectedAgents = $repo->agents->pluck('id')->toArray();
        $this->selectedProjects = $repo->projects->pluck('id')->toArray();
    }

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

        $this->repo->update([
            'organization_id' => $validated['organization_id'],
            'name' => $validated['name'],
            'source' => $validated['source'],
            'source_reference' => $validated['source_reference'],
            'source_url' => $validated['source_url'],
        ]);

        $this->repo->skills()->sync($this->selectedSkills);
        $this->repo->agents()->sync($this->selectedAgents);
        $this->repo->projects()->sync($this->selectedProjects);

        $this->redirect(route('repos.show', $this->repo), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('repos.show', $repo) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Repo') }}</flux:heading>
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
                    {{ __('Update') }}
                </flux:button>
                <flux:button href="{{ route('repos.show', $repo) }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
