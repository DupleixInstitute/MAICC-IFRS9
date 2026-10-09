<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">


        <!-- Light only: the MAIIC layout was restored on 9 October 2026 and has
             no appearance switch, so the theme is pinned to light (the saved
             choice is reset so useTheme does not follow a dark device). -->
        <script>
            (function () {
                try { localStorage.setItem('maiic.theme', 'light'); } catch (e) {}
            })();
        </script>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        @vite('resources/js/app.js')

        <!-- Scripts -->
        @routes

    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900 dark:bg-slate-900 dark:text-slate-100">
        @inertia
    </body>
</html>
