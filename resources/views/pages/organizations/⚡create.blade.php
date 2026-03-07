<?php

use App\Models\Organization;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Organization')] class extends Component {
    public string $title = '';
    public string $slug = '';

    public function updatedTitle(string $value): void
    {
        $this->slug = Str::slug($value);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug'],
        ]);

        $organization = Organization::query()->create($validated);
        auth()->user()->organizations()->attach($organization);

        $this->redirect(route('organizations.show', $organization), navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <flux:button href="{{ route('organizations.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ __('Create Organization') }}</flux:heading>
        </div>

        <form wire:submit="save" class="max-w-xl space-y-6">
            <flux:input wire:model="title" :label="__('Title')" type="text" required autofocus />
            <flux:input wire:model="slug" :label="__('Slug')" type="text" required />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Create') }}
                </flux:button>
                <flux:button href="{{ route('organizations.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
