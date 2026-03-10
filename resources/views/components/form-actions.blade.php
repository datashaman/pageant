@props([
    'submitLabel' => __('Update'),
    'cancelUrl',
    'cancelVariant' => 'ghost',
])

<div class="flex items-center gap-4">
    <flux:button variant="primary" type="submit">
        {{ $submitLabel }}
    </flux:button>
    <flux:button :variant="$cancelVariant" href="{{ $cancelUrl }}" wire:navigate>
        {{ __('Cancel') }}
    </flux:button>
</div>
