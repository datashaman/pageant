<?php

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Work Item')] class extends Component {
    public WorkItem $workItem;

    public string $project_id = '';
    public string $title = '';
    public string $description = '';

    public function mount(WorkItem $workItem): void
    {
        $userOrgIds = auth()->user()->organizations->pluck('id');
        abort_unless($userOrgIds->contains($workItem->organization_id), 403);

        $this->workItem = $workItem;
        $this->project_id = $workItem->project_id ?? '';
        $this->title = $workItem->title;
        $this->description = $workItem->description ?? '';
    }

    #[Computed]
    public function projects(): Collection
    {
        return Project::query()
            ->where('organization_id', $this->workItem->organization_id)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
        ]);

        if (empty($validated['project_id'])) {
            $validated['project_id'] = null;
        }

        $this->workItem->update($validated);

        $this->redirect(route('work-items.show', $this->workItem), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('work-items.show', $workItem) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Work Item') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-xl space-y-6">
            @if ($workItem->source_reference)
                <div>
                    <flux:label>{{ __('Source') }}</flux:label>
                    <flux:text>{{ $workItem->source }} &mdash; {{ $workItem->source_reference }}</flux:text>
                </div>
            @endif

            <flux:input wire:model="title" :label="__('Title')" type="text" required autofocus />

            <flux:textarea wire:model="description" :label="__('Description')" />

            <flux:select wire:model="project_id" :label="__('Project')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->projects as $project)
                    <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Update') }}
                </flux:button>
                <flux:button href="{{ route('work-items.show', $workItem) }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
