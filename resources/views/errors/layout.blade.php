<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    {{-- A page reached outside the app (a typed address, a bookmark, a link in an e-mail): the same plain
         explanation as the in-app error modal, in light and dark mode, with a way back. --}}
    <style>
        :root { --bg: #f1f5f9; --card: #ffffff; --text: #0f172a; --muted: #475569; --soft: #fee2e2; --icon: #dc2626; --foot: #f8fafc; --btn: #0f766e; --btnh: #115e59; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f172a; --card: #1e293b; --text: #f1f5f9; --muted: #cbd5e1; --soft: rgba(127, 29, 29, .4); --icon: #fca5a5; --foot: rgba(15, 23, 42, .6); } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .card { width: 100%; max-width: 480px; background: var(--card); border-radius: 16px; box-shadow: 0 20px 45px rgba(15, 23, 42, .18); overflow: hidden; }
        .body { display: flex; gap: 16px; padding: 24px; }
        .icon { flex: none; width: 44px; height: 44px; border-radius: 9999px; background: var(--soft); display: flex; align-items: center; justify-content: center; }
        .icon svg { width: 24px; height: 24px; color: var(--icon); }
        h1 { margin: 0; font-size: 18px; font-weight: 800; }
        p { margin: 6px 0 0; font-size: 14px; line-height: 1.55; color: var(--muted); }
        .code { margin-top: 10px; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); opacity: .8; }
        .foot { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 24px; background: var(--foot); }
        a.btn { display: inline-block; border-radius: 8px; padding: 8px 18px; font-size: 14px; font-weight: 700; text-decoration: none; color: #fff; background: var(--btn); }
        a.btn:hover { background: var(--btnh); }
        a.link { display: inline-block; padding: 8px 12px; font-size: 14px; font-weight: 600; color: var(--muted); text-decoration: none; }
    </style>
</head>
<body>
<main class="card" role="alert">
    <div class="body">
        <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/></svg>
        </div>
        <div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div class="code">Error code @yield('code')</div>
        </div>
    </div>
    <div class="foot">
        <a class="link" href="javascript:history.back()">Go back</a>
        <a class="btn" href="{{ url('/') }}">Go to the dashboard</a>
    </div>
</main>
</body>
</html>
