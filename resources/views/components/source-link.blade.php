@props([
    'source' => null,
    'sourceReference' => null,
    'sourceUrl' => null,
])

@php
    $label = $sourceReference ?: $sourceUrl;
    $safeUrl = $sourceUrl && \Illuminate\Support\Str::startsWith($sourceUrl, ['http://', 'https://'])
        ? $sourceUrl
        : null;
@endphp

@if ($label)
<span class="inline-flex items-center gap-2">
    @if ($source === 'github')
        <x-icon-github class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" />
    @endif
    @if ($safeUrl)
        <flux:link href="{{ $safeUrl }}" target="_blank">
            {{ $label }}
        </flux:link>
    @else
        <span>{{ $label }}</span>
    @endif
</span>
@endif
