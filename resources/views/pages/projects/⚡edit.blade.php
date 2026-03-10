<?php

use App\Models\Project;
use App\Models\Repo;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Project')] class extends Component {
    public Project $project;

    public string $name = '';

    public string $description = '';

    /** @var array<string> */
    public array $selectedRepos = [];

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()->organizations->contains('id', $project->organization_id), 403);

        $this->project = $project;
        $this->name = $project->name;
        $this->description = $project->description ?? '';
        $this->selectedRepos = $project->repos->pluck('id')->toArray();
    }

    #[Computed]
    public function repos()
    {
        return Repo::query()
            ->where('organization_id', $this->project->organization_id)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'selectedRepos' => ['array'],
            'selectedRepos.*' => ['string', Rule::exists('repos', 'id')->where('organization_id', $this->project->organization_id)],
        ]);

        $this->project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
        ]);

        $this->project->repos()->sync($validated['selectedRepos']);

        $this->redirect(route('projects.show', $this->project), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'projects.edit', 'project_id' => $project->id, 'project_name' => $project->name]) }}">
    <div class="flex flex-col gap-6">
        <flux:heading size="xl">{{ __('Edit Project') }}</flux:heading>

        <x-projects.form
            :repos="$this->repos"
            :submit-label="__('Update Project')"
            :cancel-url="route('projects.show', $project)"
        />
    </div>
</div>
