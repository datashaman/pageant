@props([
    'agents',
    'repos',
    'submitLabel' => __('Update'),
    'cancelUrl',
])

<form wire:submit="save" class="max-w-xl space-y-6">
    <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />
    <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
    <flux:input wire:model="argumentHint" :label="__('Argument Hint')" type="text" />
    <flux:input wire:model="license" :label="__('License')" type="text" />
    <flux:checkbox wire:model="enabled" :label="__('Enabled')" />
    <flux:input wire:model="path" :label="__('Path')" type="text" />

    <flux:textarea wire:model="allowedToolsText" :label="__('Allowed Tools')" :description="__('Comma-separated list of tool names')" rows="2" />

    <flux:input wire:model="provider" :label="__('Provider')" type="text" />
    <flux:input wire:model="model" :label="__('Model')" type="text" />
    <flux:textarea wire:model="context" :label="__('Context')" rows="3" />

    <flux:select wire:model="agent_id" :label="__('Primary Agent')">
        <option value="">{{ __('None') }}</option>
        @foreach ($agents as $agent)
            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
        @endforeach
    </flux:select>

    <flux:select wire:model="source" :label="__('Source')">
        <option value="">{{ __('None') }}</option>
        <option value="github">{{ __('GitHub') }}</option>
        <option value="gitlab">{{ __('GitLab') }}</option>
        <option value="bitbucket">{{ __('Bitbucket') }}</option>
    </flux:select>

    <flux:input wire:model="sourceReference" :label="__('Source Reference')" type="text" />
    <flux:input wire:model="sourceUrl" :label="__('Source URL')" type="text" />

    @if ($agents->isNotEmpty())
        <fieldset class="space-y-2">
            <flux:heading size="sm">{{ __('Associated Agents') }}</flux:heading>
            @foreach ($agents as $agent)
                <flux:checkbox wire:model="selectedAgents" :label="$agent->name" :value="$agent->id" />
            @endforeach
        </fieldset>
    @endif

    @if ($repos->isNotEmpty())
        <fieldset class="space-y-2">
            <flux:heading size="sm">{{ __('Associated Repos') }}</flux:heading>
            @foreach ($repos as $repo)
                <flux:checkbox wire:model="selectedRepos" :label="$repo->display_name" :value="$repo->id" />
            @endforeach
        </fieldset>
    @endif

    <x-form-actions :submit-label="$submitLabel" :cancel-url="$cancelUrl" />
</form>
