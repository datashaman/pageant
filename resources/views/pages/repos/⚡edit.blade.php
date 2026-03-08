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

    public string $name = '';

    public array $selectedSkills = [];
    public array $selectedAgents = [];
    public array $selectedProjects = [];

    public function mount(Repo $repo): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->repo->organization_id), 403);

        $this->name = $repo->name;

        $this->selectedSkills = $repo->skills->pluck('id')->toArray();
        $this->selectedAgents = $repo->agents->pluck('id')->toArray();
        $this->selectedProjects = $repo->projects->pluck('id')->toArray();
    }

    #[Computed]
    public function availableSkills(): Collection
    {
        return Skill::query()->where('organization_id', $this->repo->organization_id)->get();
    }

    #[Computed]
    public function availableAgents(): Collection
    {
        return Agent::query()->where('organization_id', $this->repo->organization_id)->get();
    }

    #[Computed]
    public function availableProjects(): Collection
    {
        return Project::query()->where('organization_id', $this->repo->organization_id)->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'selectedSkills' => ['array'],
            'selectedSkills.*' => ['uuid'],
            'selectedAgents' => ['array'],
            'selectedAgents.*' => ['uuid'],
            'selectedProjects' => ['array'],
            'selectedProjects.*' => ['uuid'],
        ]);

        $this->repo->update([
            'name' => $validated['name'],
        ]);

        $this->repo->skills()->sync($this->selectedSkills);
        $this->repo->agents()->sync($this->selectedAgents);
        $this->repo->projects()->sync($this->selectedProjects);

        $this->redirect(route('repos.show', $this->repo), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'repos.edit', 'repo_id' => $repo->id, 'repo_name' => $repo->name]) }}">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('repos.show', $repo) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Repo') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-xl space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

            <div>
                <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Repository') }}</flux:heading>
                @if ($repo->source_url)
                    <flux:link href="{{ $repo->source_url }}" target="_blank">{{ $repo->source_reference }}</flux:link>
                @else
                    <flux:text>{{ $repo->source_reference }}</flux:text>
                @endif
            </div>

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
