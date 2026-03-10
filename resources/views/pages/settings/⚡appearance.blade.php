<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="space-y-8">
            <div>
                <flux:label class="mb-2 block">{{ __('Theme') }}</flux:label>
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>
            </div>

            <div
                x-data="{
                    colorblind: localStorage.getItem('pageant-colorblind') || 'none',
                    init() {
                        $watch('colorblind', value => {
                            localStorage.setItem('pageant-colorblind', value);
                            if (value && value !== 'none') {
                                document.documentElement.setAttribute('data-colorblind', value);
                            } else {
                                document.documentElement.removeAttribute('data-colorblind');
                            }
                        });
                    }
                }"
            >
                <flux:label class="mb-2 block">{{ __('Color vision') }}</flux:label>
                <select
                    x-model="colorblind"
                    class="w-full max-w-xs rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:ring-2 focus:ring-zinc-400 focus:ring-offset-2 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:ring-zinc-500"
                >
                    <option value="none">{{ __('Default') }}</option>
                    <option value="deuteranopia">{{ __('Deuteranopia-friendly') }}</option>
                    <option value="protanopia">{{ __('Protanopia-friendly') }}</option>
                    <option value="tritanopia">{{ __('Tritanopia-friendly') }}</option>
                </select>
                <flux:text class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Uses color palettes that remain distinguishable for common forms of color vision deficiency.') }}
                </flux:text>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
