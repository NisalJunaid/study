<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Focus Lab</title>
    @vite(['resources/css/app.css'])
</head>
<body style="display:grid;place-items:center;min-height:100vh;padding:2rem">
    <section class="card" style="max-width:720px;width:100%">
        <span class="pill">O'Level Study Help</span>
        <h1 style="margin:.75rem 0">Focus Lab is ready</h1>
        <p class="muted">Sign in to continue to your dashboard. New users can register as students.</p>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap">
            <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-secondary">Register</a>
            @endif
        </div>
    </section>
</body>
</html>
