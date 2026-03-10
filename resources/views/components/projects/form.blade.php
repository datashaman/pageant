@props([
    'repos',
    'submitLabel',
    'cancelUrl',
])

<form wire:submit="save" class="space-y-6">
    <flux:input wire:model="name" label="{{ __('Name') }}" placeholder="{{ __('Project name') }}" />

    <flux:textarea wire:model="description" label="{{ __('Description') }}" placeholder="{{ __('Project description') }}" rows="4" />

    @if ($repos->isNotEmpty())
        <flux:checkbox.group wire:model="selectedRepos" label="{{ __('Repos') }}">
            @foreach ($repos as $repo)
                <flux:checkbox :value="$repo->id" :label="$repo->display_name" />
            @endforeach
        </flux:checkbox.group>
    @endif

    <x-form-actions :submit-label="$submitLabel" :cancel-url="$cancelUrl" />
</form>
