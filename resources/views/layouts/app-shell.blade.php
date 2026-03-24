<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? "Focus Lab" }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">Focus Lab</div>
        <p class="muted" style="margin-top:0">{{ $navDescription ?? "O'Level Study Help" }}</p>
        @yield('sidebar')
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1 style="margin:0">{{ $heading ?? 'Dashboard' }}</h1>
                <p class="muted" style="margin:.25rem 0 0">{{ $subheading ?? 'Build momentum with focused practice.' }}</p>
            </div>
            @auth
                <div>
                    <span class="pill">{{ ucfirst(auth()->user()->role) }}</span>
                    <span class="muted" style="margin-left:.5rem">{{ auth()->user()->name }}</span>
                </div>
            @endauth
        </header>

        @yield('content')
    </main>
</div>
</body>
</html>
