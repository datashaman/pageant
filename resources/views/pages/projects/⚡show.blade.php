<?php

use App\Models\Project;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Project')] class extends Component {
    public Project $project;

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()->organizations->contains('id', $project->organization_id), 403);

        $this->project = $project->load(['organization', 'repos', 'workItems']);
    }

    public function delete(): void
    {
        abort_unless(auth()->user()->organizations->contains('id', $this->project->organization_id), 403);

        $this->project->delete();

        $this->redirect(route('projects.index'), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('projects.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $project->name }}</flux:heading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('projects.edit', $project) }}" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button variant="danger" wire:click="$dispatch('open-modal', { id: 'confirm-delete' })">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>

        <div class="max-w-xl space-y-4">
            <div>
                <flux:label>{{ __('Organization') }}</flux:label>
                <flux:link href="{{ route('organizations.show', $project->organization) }}" wire:navigate>
                    {{ $project->organization->name }}
                </flux:link>
            </div>

            <div>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:text>{{ $project->description ?: '—' }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Created') }}</flux:label>
                <flux:text>{{ $project->created_at->format('M j, Y g:i A') }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Updated') }}</flux:label>
                <flux:text>{{ $project->updated_at->format('M j, Y g:i A') }}</flux:text>
            </div>
        </div>

        @if ($project->repos->isNotEmpty())
            <flux:heading size="lg" class="!mt-8">{{ __('Repos') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Repository') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($project->repos as $repo)
                        <flux:table.row :key="$repo->id">
                            <flux:table.cell>
                                <flux:link href="{{ route('repos.show', $repo) }}" wire:navigate>
                                    {{ $repo->name }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($repo->source_url)
                                    <flux:link href="{{ $repo->source_url }}" target="_blank">
                                        {{ $repo->source_reference }}
                                    </flux:link>
                                @else
                                    {{ $repo->source_reference }}
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        @if ($project->workItems->isNotEmpty())
            <flux:heading size="lg" class="!mt-8">{{ __('Work Items') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Issue') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($project->workItems as $workItem)
                        <flux:table.row :key="$workItem->id">
                            <flux:table.cell>
                                <flux:link href="{{ route('work-items.show', $workItem) }}" wire:navigate>
                                    {{ $workItem->title }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($workItem->source_url)
                                    <flux:link href="{{ $workItem->source_url }}" target="_blank">
                                        {{ $workItem->source_reference }}
                                    </flux:link>
                                @else
                                    {{ $workItem->source_reference }}
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Project') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $project->name]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
