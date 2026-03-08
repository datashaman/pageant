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
    </head>
    <body class="bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 flex items-center justify-center min-h-screen">
        <div class="text-center space-y-6">
            <h1 class="text-3xl font-semibold">{{ config('app.name', 'Pageant') }}</h1>

            @if ($errors->has('email'))
                <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('email') }}</p>
            @endif

            @auth
                <a href="{{ route('dashboard') }}" class="inline-block px-6 py-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-md font-medium hover:opacity-90 transition">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ route('auth.github') }}" class="inline-flex items-center gap-2 px-6 py-2 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 rounded-md font-medium hover:opacity-90 transition">
                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                    Sign in with GitHub
                </a>
            @endauth
        </div>
    </body>
</html>
