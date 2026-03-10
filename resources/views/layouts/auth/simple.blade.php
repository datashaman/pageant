<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 antialiased">
        <header class="fixed left-0 right-0 top-0 z-10 border-b border-zinc-200 bg-white/80 px-6 py-4 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/80">
            <div class="mx-auto flex max-w-3xl items-center justify-center">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-medium text-zinc-900 dark:text-zinc-100" wire:navigate>
                    <x-app-logo-icon class="size-8 fill-current" />
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name', 'Pageant') }}</span>
                </a>
            </div>
        </header>

        <div class="flex min-h-svh flex-col items-center justify-center px-6 pb-16 pt-24 md:px-10">
            <div class="flex w-full max-w-sm flex-col gap-6">
                {{ $slot }}
            </div>
        </div>
        @fluxScripts
    </body>
</html>
