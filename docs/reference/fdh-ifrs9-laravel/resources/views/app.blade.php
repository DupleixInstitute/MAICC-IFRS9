<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Theme: apply the saved (or OS-preferred) colour scheme BEFORE paint so
             there is no flash of the wrong theme. The toggle persists 'fdh.theme'. -->
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('fdh.theme');
                    var dark = t ? (t === 'dark') : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (dark) document.documentElement.classList.add('dark');
                } catch (e) {}
            })();
        </script>

        <!-- Fonts: self-contained system stack via Tailwind font-sans; no external CDN. -->

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
