<?php

use App\Models\Project;
use App\Models\Repo;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Project')] class extends Component {
    public string $name = '';

    public string $description = '';

    /** @var array<string> */
    public array $selectedRepos = [];

    #[Computed]
    public function repos()
    {
        return Repo::query()->forCurrentOrganization()->orderBy('name')->get();
    }

    public function save(): void
    {
        $organizationId = auth()->user()->currentOrganizationId();
        abort_unless($organizationId, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'selectedRepos' => ['array'],
            'selectedRepos.*' => ['string', Rule::exists('repos', 'id')->where('organization_id', $organizationId)],
        ]);

        $project = Project::create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
        ]);

        $project->repos()->sync($validated['selectedRepos']);

        $this->redirect(route('projects.show', $project), navigate: true);
    }
}; ?>

<div class="w-full" data-chat-context="{{ json_encode(['page' => 'projects.create']) }}">
    <div class="flex flex-col gap-6">
        <flux:heading size="xl">{{ __('Create Project') }}</flux:heading>

        <x-projects.form
            :repos="$this->repos"
            :submit-label="__('Create Project')"
            :cancel-url="route('projects.index')"
        />
    </div>
</div>
