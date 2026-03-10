<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 flex flex-col antialiased">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-6 sm:px-8">
            <header class="-mx-6 relative flex items-center justify-center border-b border-zinc-200 px-6 py-5 sm:-mx-8 sm:px-8 dark:border-zinc-800">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-medium text-zinc-900 dark:text-zinc-100" wire:navigate>
                    <x-app-logo-icon class="size-8 fill-current" />
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name', 'Pageant') }}</span>
                </a>
            </header>

            <main class="flex flex-1 flex-col items-center justify-center py-8">
                <div class="w-full max-w-xl space-y-8 text-center">
                <div class="space-y-4">
                    <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
                        {{ __('AI Agent Orchestration for GitHub') }}
                    </h1>
                    <p class="text-base text-zinc-600 dark:text-zinc-400">
                        {{ __('Connect repositories with intelligent agents. Create workspaces, assign agents, and automate—all integrated with GitHub.') }}
                    </p>
                </div>

                @if ($errors->has('email'))
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('email') }}</p>
                @endif

                <flux:button href="{{ route('auth.github') }}" variant="outline" class="!border-zinc-300 !text-zinc-900 hover:!bg-zinc-100 dark:!border-zinc-600 dark:!text-zinc-100 dark:hover:!bg-zinc-800">
                    <x-icon-github class="size-5 me-2" />
                    {{ __('Sign in with GitHub') }}
                </flux:button>

                @if (app()->environment('local'))
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:link :href="route('login')" wire:navigate>{{ __('Log in with email and password') }}</flux:link>
                    </p>
                @endif
                </div>
            </main>

            <footer class="py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                &copy; {{ date('Y') }} {{ config('app.name', 'Pageant') }}
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
