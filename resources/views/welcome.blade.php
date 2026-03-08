<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Pageant') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script>
            (function() {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
    </head>
    <body class="bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 min-h-screen flex flex-col">
        <header class="w-full px-6 py-4 flex items-center justify-between max-w-5xl mx-auto">
            <span class="text-lg font-semibold tracking-tight">{{ config('app.name', 'Pageant') }}</span>

            <nav>
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-md text-sm font-medium hover:opacity-90 transition">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('auth.github') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-md text-sm font-medium hover:opacity-90 transition">
                        <svg class="size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        Sign in with GitHub
                    </a>
                @endauth
            </nav>
        </header>

        <main class="flex-1 flex items-center justify-center px-6">
            <div class="max-w-3xl mx-auto text-center space-y-10">
                <div class="space-y-4">
                    <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">
                        AI Agent Orchestration for GitHub
                    </h1>
                    <p class="text-lg sm:text-xl text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                        Manage your repositories, projects, and work items with intelligent agents that understand your codebase.
                    </p>
                </div>

                @if ($errors->has('email'))
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('email') }}</p>
                @endif

                @auth
                    <div>
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-lg font-medium hover:opacity-90 transition text-base">
                            Go to Dashboard
                        </a>
                    </div>
                @else
                    <div>
                        <a href="{{ route('auth.github') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-lg font-medium hover:opacity-90 transition text-base">
                            <svg class="size-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                            Get Started with GitHub
                        </a>
                    </div>
                @endauth

                <div class="grid sm:grid-cols-3 gap-6 pt-6 text-left">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="size-5 text-zinc-500 dark:text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
                            <h3 class="font-semibold">Repository Management</h3>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Import and organize your GitHub repositories with automated setup and configuration.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="size-5 text-zinc-500 dark:text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" /></svg>
                            <h3 class="font-semibold">Intelligent Agents</h3>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Deploy AI agents that work across your projects to handle tasks, reviews, and automation.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="size-5 text-zinc-500 dark:text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m-13.5-3h13.5m-13.5 0A2.25 2.25 0 0 0 4.5 9v9a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18V9m-13.5 0h13.5" /></svg>
                            <h3 class="font-semibold">Work Item Tracking</h3>
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Track projects and work items with seamless GitHub integration and real-time updates.
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <footer class="w-full px-6 py-6 text-center text-sm text-zinc-500 dark:text-zinc-500">
            &copy; {{ date('Y') }} {{ config('app.name', 'Pageant') }}
        </footer>
    </body>
</html>
