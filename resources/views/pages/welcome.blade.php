<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Focus Lab</title>
    @vite(['resources/css/app.css'])
</head>
<body class="centered-screen">
    <section class="card centered-shell">
        <span class="pill">O'Level Study Help</span>
        <h1 style="margin:.75rem 0">Focus Lab is ready</h1>
        <p class="muted">Sign in to continue to your dashboard. New users can register as students.</p>

        <div class="actions-inline" style="justify-content:flex-start;margin-top:1.25rem;">
            <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn">Register</a>
            @endif
        </div>
    </section>
</body>
</html>
