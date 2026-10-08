<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">


        <!-- The theme before the page paints, so there is no flash of the wrong
             theme: the saved choice (maiic.theme) or, for "follow the device"
             and for a new browser, the device setting (spec v4 section 11.5). -->
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('maiic.theme');
                    var dark = t === 'dark' || ((!t || t === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) document.documentElement.classList.add('dark');
                } catch (e) {}
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
