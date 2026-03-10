@props([
    'repo',
])

@if ($repo->source_url)
    <flux:link href="{{ $repo->source_url }}" target="_blank">
        {{ $repo->display_name }}
    </flux:link>
@else
    {{ $repo->display_name }}
@endif
