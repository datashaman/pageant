<?php

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Work Item')] class extends Component {
    public string $organization_id = '';
    public string $project_id = '';
    public string $title = '';
    public string $description = '';
    public string $board_id = '';
    public string $source = '';
    public string $source_reference = '';
    public string $source_url = '';

    #[Computed]
    public function organizations(): Collection
    {
        return auth()->user()->organizations()->orderBy('title')->get();
    }

    #[Computed]
    public function projects(): Collection
    {
        if (! $this->organization_id) {
            return collect();
        }

        return Project::query()
            ->where('organization_id', $this->organization_id)
            ->orderBy('name')
            ->get();
    }

    public function updatedOrganizationId(): void
    {
        $this->project_id = '';
    }

    public function save(): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($this->organization_id), 403);

        $validated = $this->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'board_id' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'string', 'in:github,gitlab,jira'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
        ]);

        if (empty($validated['project_id'])) {
            $validated['project_id'] = null;
        }

        $workItem = WorkItem::query()->create($validated);

        $this->redirect(route('work-items.show', $workItem), navigate: true);
    }
}; ?>

<div>
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('work-items.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Create Work Item') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-xl space-y-6">
            <flux:select wire:model.live="organization_id" :label="__('Organization')" required>
                <flux:select.option value="">{{ __('Select Organization') }}</flux:select.option>
                @foreach ($this->organizations as $organization)
                    <flux:select.option :value="$organization->id">{{ $organization->title }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="project_id" :label="__('Project')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->projects as $project)
                    <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="title" :label="__('Title')" type="text" required autofocus />

            <flux:textarea wire:model="description" :label="__('Description')" />

            <flux:select wire:model="source" :label="__('Source')" required>
                <flux:select.option value="">{{ __('Select Source') }}</flux:select.option>
                <flux:select.option value="github">{{ __('GitHub') }}</flux:select.option>
                <flux:select.option value="gitlab">{{ __('GitLab') }}</flux:select.option>
                <flux:select.option value="jira">{{ __('Jira') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="board_id" :label="__('Board ID')" type="text" />

            <flux:input wire:model="source_reference" :label="__('Source Reference')" type="text" />

            <flux:input wire:model="source_url" :label="__('Source URL')" type="url" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Create') }}
                </flux:button>
                <flux:button href="{{ route('work-items.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
