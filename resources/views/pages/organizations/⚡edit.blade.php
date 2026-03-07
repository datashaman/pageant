<?php

use App\Models\Organization;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Organization')] class extends Component {
    public Organization $organization;

    public string $name = '';
    public string $slug = '';

    public function mount(Organization $organization): void
    {
        abort_unless(auth()->user()->organizations()->where('organizations.id', $organization->id)->exists(), 403);

        $this->organization = $organization;
        $this->name = $organization->name;
        $this->slug = $organization->slug;
    }

    public function update(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,' . $this->organization->id],
        ]);

        $this->organization->update($validated);

        $this->redirect(route('organizations.show', $this->organization), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('organizations.show', $organization) }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Edit Organization') }}</flux:heading>
        </div>

        <form wire:submit="update" class="max-w-xl space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />
            <flux:input wire:model="slug" :label="__('Slug')" type="text" required />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Update') }}
                </flux:button>
                <flux:button href="{{ route('organizations.show', $organization) }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
