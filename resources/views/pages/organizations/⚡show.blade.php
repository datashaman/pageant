<?php

use App\Models\Organization;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Organization')] class extends Component {
    public Organization $organization;

    public function mount(Organization $organization): void
    {
        abort_unless(auth()->user()->organizations()->where('organizations.id', $organization->id)->exists(), 403);

        $this->organization = $organization->loadCount(['repos', 'skills', 'agents', 'projects', 'workItems']);
    }

    public function confirmDelete(): void
    {
        $this->dispatch('open-modal', id: 'confirm-delete');
    }

    public function delete(): void
    {
        $this->organization->delete();

        $this->redirect(route('organizations.index'), navigate: true);
    }
}; ?>

<div>title">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:button href="{{ route('organizations.index') }}" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
                <flux:heading size="xl">{{ $organization->title }}</flux:heading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('organizations.edit', $organization) }}" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button variant="danger" wire:click="confirmDelete">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>

        <div class="max-w-xl space-y-4">
            <div>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:text>{{ $organization->title }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Slug') }}</flux:label>
                <flux:text>{{ $organization->slug }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Created') }}</flux:label>
                <flux:text>{{ $organization->created_at->format('M j, Y g:i A') }}</flux:text>
            </div>

            <div>
                <flux:label>{{ __('Updated') }}</flux:label>
                <flux:text>{{ $organization->updated_at->format('M j, Y g:i A') }}</flux:text>
            </div>
        </div>

        <flux:heading size="lg" class="!mt-8">{{ __('Related Resources') }}</flux:heading>

        <div class="grid max-w-xl grid-cols-2 gap-4 sm:grid-cols-3">
            <flux:link href="{{ route('agents.index') }}" wire:navigate class="block rounded-lg border p-4 text-center">
                <flux:text class="text-2xl font-bold">{{ $organization->agents_count }}</flux:text>
                <flux:text>{{ __('Agents') }}</flux:text>
            </flux:link>

            <flux:link href="{{ route('repos.index') }}" wire:navigate class="block rounded-lg border p-4 text-center">
                <flux:text class="text-2xl font-bold">{{ $organization->repos_count }}</flux:text>
                <flux:text>{{ __('Repos') }}</flux:text>
            </flux:link>

            <flux:link href="{{ route('skills.index') }}" wire:navigate class="block rounded-lg border p-4 text-center">
                <flux:text class="text-2xl font-bold">{{ $organization->skills_count }}</flux:text>
                <flux:text>{{ __('Skills') }}</flux:text>
            </flux:link>

            <flux:link href="{{ route('projects.index') }}" wire:navigate class="block rounded-lg border p-4 text-center">
                <flux:text class="text-2xl font-bold">{{ $organization->projects_count }}</flux:text>
                <flux:text>{{ __('Projects') }}</flux:text>
            </flux:link>

            <flux:link href="{{ route('work-items.index') }}" wire:navigate class="block rounded-lg border p-4 text-center">
                <flux:text class="text-2xl font-bold">{{ $organization->work_items_count }}</flux:text>
                <flux:text>{{ __('Work Items') }}</flux:text>
            </flux:link>
        </div>

        <flux:modal name="confirm-delete">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Delete Organization') }}</flux:heading>
                <flux:text>{{ __('Are you sure you want to delete ":title"? This action cannot be undone.', ['title' => $organization->title]) }}</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button x-on:click="$flux.modal.close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
