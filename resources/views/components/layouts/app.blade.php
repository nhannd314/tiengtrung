@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        {{-- Applied before first paint to avoid a flash of the wrong theme. Theme: light (default) | dark | system. --}}
        <script>
            window.applyTheme = (theme) => {
                const dark = theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = theme;
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            };

            let savedTheme = null;
            try { savedTheme = localStorage.getItem('theme'); } catch {}
            window.applyTheme(['light', 'dark', 'system'].includes(savedTheme) ? savedTheme : 'light');
        </script>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
        <x-site-header />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-stone-200 py-6 text-center text-sm text-stone-500 dark:border-stone-800 dark:text-stone-400">
            &copy; {{ date('Y') }} {{ config('app.name') }}. 学而时习之 — Học đi đôi với hành.
        </footer>
    </body>
</html>
