@props([
    'availableProviders',
    'availableModels',
    'eventCategories',
    'toolCategories',
    'skills',
    'workspaces',
    'selectedEventKeys' => [],
    'submitLabel' => __('Update'),
    'cancelUrl',
    'submitAction' => 'update',
])

<form wire:submit="{{ $submitAction }}" x-data="{ tab: 'general', eventsSubtab: 'github', toolsSubtab: 'github' }">
    <div class="mb-6 flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <button type="button" @click="tab = 'general'"
            :class="tab === 'general' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
            class="-mb-px px-4 py-2 text-sm font-medium transition">
            {{ __('General') }}
        </button>
        <button type="button" @click="tab = 'events'"
            :class="tab === 'events' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
            class="-mb-px px-4 py-2 text-sm font-medium transition">
            {{ __('Events') }}
        </button>
        <button type="button" @click="tab = 'tools'"
            :class="tab === 'tools' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
            class="-mb-px px-4 py-2 text-sm font-medium transition">
            {{ __('Tools') }}
        </button>
        <button type="button" @click="tab = 'skills'"
            :class="tab === 'skills' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
            class="-mb-px px-4 py-2 text-sm font-medium transition">
            {{ __('Skills') }}
        </button>
        <button type="button" @click="tab = 'workspaces'"
            :class="tab === 'workspaces' ? 'border-b-2 border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
            class="-mb-px px-4 py-2 text-sm font-medium transition">
            {{ __('Workspaces') }}
        </button>
    </div>

    <div class="space-y-6">
        {{-- General tab --}}
        <div x-show="tab === 'general'" x-cloak>
            <div class="max-w-2xl space-y-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

                <flux:textarea wire:model="description" :label="__('Description')" />

                <flux:select wire:model="provider" :label="__('Provider')">
                    <option value="">{{ __('Select Provider') }}</option>
                    <option value="anthropic" @disabled(! $availableProviders['anthropic'])>{{ __('Anthropic') }}</option>
                    <option value="openai" @disabled(! $availableProviders['openai'])>{{ __('OpenAI') }}</option>
                    <option value="gemini" @disabled(! $availableProviders['gemini'])>{{ __('Gemini') }}</option>
                </flux:select>

                <flux:select wire:model="model" :label="__('Model')">
                    <option value="inherit">{{ __('Default') }}</option>
                    <optgroup label="{{ __('Strategy') }}">
                        <option value="cheapest">{{ __('Cheapest Model') }}</option>
                        <option value="smartest">{{ __('Smartest Model') }}</option>
                    </optgroup>
                    @if ($availableModels)
                        <optgroup label="{{ __('Models') }}">
                            @foreach ($availableModels as $modelId => $modelLabel)
                                <option value="{{ $modelId }}">{{ $modelLabel }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </flux:select>

                <flux:select wire:model="permission_mode" :label="__('Permission Mode')">
                    <option value="">{{ __('Select Permission Mode') }}</option>
                    <option value="full">{{ __('Full') }}</option>
                    <option value="limited">{{ __('Limited') }}</option>
                </flux:select>

                <flux:input wire:model="max_turns" :label="__('Max Turns')" type="number" min="1" />

                <flux:checkbox wire:model="background" :label="__('Background')" />

                <flux:select wire:model="isolation" :label="__('Isolation')">
                    <option value="">{{ __('None') }}</option>
                    <option value="worktree">{{ __('Worktree') }}</option>
                </flux:select>
            </div>
        </div>

        {{-- Events tab --}}
        <div x-show="tab === 'events'" x-cloak>
            <div class="flex gap-6">
                <div class="flex w-36 shrink-0 flex-col gap-1 border-r border-zinc-200 pr-6 dark:border-zinc-700">
                    @foreach (['github' => 'GitHub', 'pageant' => 'Pageant'] as $key => $label)
                        <button type="button" @click="eventsSubtab = '{{ $key }}'"
                            :class="eventsSubtab === '{{ $key }}' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                            class="rounded-md px-3 py-2 text-left text-sm font-medium transition">
                            {{ __($label) }}
                        </button>
                    @endforeach
                </div>
                <div class="min-w-0 grow">
                    <div class="mb-4 flex items-center gap-2">
                        <flux:button size="xs" wire:click="selectAllEvents">{{ __('Check all') }}</flux:button>
                        <flux:button size="xs" wire:click="deselectAllEvents">{{ __('Uncheck all') }}</flux:button>
                    </div>
                    <flux:checkbox.group wire:model="selectedEventKeys">
                        @foreach ($eventCategories as $category => $sections)
                            <div x-show="eventsSubtab === '{{ $category }}'" x-cloak>
                                @foreach ($sections as $section)
                                    <div class="mb-4">
                                        <flux:heading size="xs" class="mb-2">{{ $section['label'] }}</flux:heading>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            @foreach ($section['checkboxes'] as $checkbox)
                                                <flux:checkbox :label="$checkbox['label']" :value="$checkbox['value']" />
                                            @endforeach
                                        </div>
                                        @foreach ($section['filters'] as $eventName => $filterTypes)
                                            @php
                                                $hasSelectedAction = collect($selectedEventKeys)->contains(fn ($k) => $k === $eventName || str_starts_with($k, $eventName . '.'));
                                            @endphp
                                            @if ($hasSelectedAction)
                                                <div class="mt-3 space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Filters (optional)') }}</flux:text>
                                                    @if (in_array('labels', $filterTypes))
                                                        <flux:input wire:model="eventFilters.{{ $eventName }}.labels" :label="__('Labels')" placeholder="bug, enhancement" size="sm" />
                                                    @endif
                                                    @if (in_array('base_branch', $filterTypes))
                                                        <flux:input wire:model="eventFilters.{{ $eventName }}.base_branch" :label="__('Base Branch')" placeholder="main" size="sm" />
                                                    @endif
                                                    @if (in_array('branches', $filterTypes))
                                                        <flux:input wire:model="eventFilters.{{ $eventName }}.branches" :label="__('Branches')" placeholder="main, release/*" size="sm" />
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </flux:checkbox.group>
                </div>
            </div>
        </div>

        {{-- Tools tab --}}
        <div x-show="tab === 'tools'" x-cloak>
            <div class="flex gap-6">
                <div class="flex w-36 shrink-0 flex-col gap-1 border-r border-zinc-200 pr-6 dark:border-zinc-700">
                    @foreach (['github' => 'GitHub', 'pageant' => 'Pageant', 'worktree' => 'Worktree'] as $key => $label)
                        <button type="button" @click="toolsSubtab = '{{ $key }}'"
                            :class="toolsSubtab === '{{ $key }}' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                            class="rounded-md px-3 py-2 text-left text-sm font-medium transition">
                            {{ __($label) }}
                        </button>
                    @endforeach
                </div>
                <div class="min-w-0 grow">
                    @foreach ($toolCategories as $category => $groups)
                        <div x-show="toolsSubtab === '{{ $category }}'" x-cloak>
                            <div class="mb-4 flex items-center gap-2">
                                <flux:button size="xs" wire:click="selectToolsByCategory('{{ $category }}')">{{ __('Check all') }}</flux:button>
                                <flux:button size="xs" wire:click="deselectToolsByCategory('{{ $category }}')">{{ __('Uncheck all') }}</flux:button>
                            </div>
                            <flux:checkbox.group wire:model="selectedTools">
                                @foreach ($groups as $group => $tools)
                                    <div class="mb-4">
                                        <flux:heading size="xs" class="mb-2">{{ $group }}</flux:heading>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            @foreach ($tools as $name => $description)
                                                <flux:checkbox :label="$description" :value="$name" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </flux:checkbox.group>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Skills tab --}}
        <div x-show="tab === 'skills'" x-cloak>
            <div class="max-w-2xl space-y-4">
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Selected skills are injected into the agent\'s context at startup. Agents do not inherit skills from the parent conversation.') }}
                </flux:text>
                @if ($skills->isNotEmpty())
                    <flux:checkbox.group wire:model="selectedSkills">
                        @foreach ($skills as $skill)
                            <div>
                                <flux:checkbox :label="$skill->name" :value="$skill->id" />
                                @if ($skill->description)
                                    <flux:text class="ml-7 text-xs text-zinc-500 dark:text-zinc-400">{{ $skill->description }}</flux:text>
                                @endif
                            </div>
                        @endforeach
                    </flux:checkbox.group>
                @else
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ __('No skills available for this organization.') }}
                    </flux:text>
                @endif
            </div>
        </div>

        {{-- Repos tab --}}
        <div x-show="tab === 'workspaces'" x-cloak>
            <div class="max-w-2xl space-y-4">
                @if ($workspaces->isNotEmpty())
                    <flux:checkbox.group wire:model="selectedRepos">
                        @foreach ($workspaces as $workspace)
                            <flux:checkbox :label="$workspace->name" :value="$workspace->id" />
                        @endforeach
                    </flux:checkbox.group>
                @else
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ __('No workspaces available for this organization.') }}
                    </flux:text>
                @endif
            </div>
        </div>

        <x-form-actions :submit-label="$submitLabel" :cancel-url="$cancelUrl" />
    </div>
</form>
