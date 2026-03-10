@props([
    'backUrl',
    'title',
    'editUrl',
])

<div class="flex items-center justify-between">
    <div class="flex items-center gap-4">
        <flux:button href="{{ $backUrl }}" wire:navigate>
            {{ __('Back') }}
        </flux:button>
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if (isset($titleExtras))
            {{ $titleExtras }}
        @endif
    </div>
    <div class="flex items-center gap-2">
        <flux:button href="{{ $editUrl }}" wire:navigate>
            {{ __('Edit') }}
        </flux:button>
        @if (isset($delete))
            {{ $delete }}
        @else
            <flux:button variant="danger" wire:click="confirmDelete">
                {{ __('Delete') }}
            </flux:button>
        @endif
    </div>
</div>
