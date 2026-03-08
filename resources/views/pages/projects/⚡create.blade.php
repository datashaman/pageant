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

        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="name" label="{{ __('Name') }}" placeholder="{{ __('Project name') }}" />

            <flux:textarea wire:model="description" label="{{ __('Description') }}" placeholder="{{ __('Project description') }}" rows="4" />

            @if ($this->repos->isNotEmpty())
                <flux:checkbox.group wire:model="selectedRepos" label="{{ __('Repos') }}">
                    @foreach ($this->repos as $repo)
                        <flux:checkbox :value="$repo->id" :label="$repo->name" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Create Project') }}</flux:button>
                <flux:button href="{{ route('projects.index') }}" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</div>
