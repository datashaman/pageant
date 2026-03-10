@props([
    'heading',
    'description',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-600']) }}>
    @if (isset($icon))
        {{ $icon }}
    @endif
    <flux:heading size="lg" class="mt-4">{{ $heading }}</flux:heading>
    <flux:text class="mt-1 max-w-sm">{{ $description }}</flux:text>
    @if (isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
