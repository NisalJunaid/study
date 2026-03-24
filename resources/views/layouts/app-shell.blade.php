<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Focus Lab' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="shell" data-shell>
    <header class="shell-topbar card">
        <button class="menu-trigger" type="button" aria-controls="app-sidebar" aria-expanded="false" data-nav-toggle aria-label="Open menu">
            ☰
        </button>

        <a class="topbar-brand" href="{{ auth()->check() && auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard') }}">Focus Lab</a>

        @auth
            <div class="topbar-user muted text-sm">{{ auth()->user()->name }}</div>
        @else
            <div></div>
        @endauth
    </header>

    <div class="sidebar-overlay" data-nav-overlay></div>

    <aside class="sidebar" id="app-sidebar" data-sidebar>
        <div class="sidebar-top row-between">
            <div>
                <div class="brand">Focus Lab</div>
                <p class="muted mt-0">{{ $navDescription ?? "O'Level Study Help" }}</p>
            </div>
            <button class="mobile-nav-close" type="button" aria-label="Close navigation" data-nav-close>✕</button>
        </div>

        @yield('sidebar')
    </aside>

    <main class="main">
        @if(!($minimalHeader ?? false))
            <header class="topbar card">
                <div class="section-title">
                    <h1 class="h0">{{ $heading ?? 'Dashboard' }}</h1>
                    <p class="muted">{{ $subheading ?? 'Build momentum with focused practice.' }}</p>
                </div>
            </header>
        @endif

        @include('components.admin.flash')

        <section class="page-content">
            @yield('content')
        </section>
    </main>
</div>

<script>
    (() => {
        const shell = document.querySelector('[data-shell]');
        if (!shell) return;

        const overlay = shell.querySelector('[data-nav-overlay]');
        const toggleButton = shell.querySelector('[data-nav-toggle]');
        const closeButton = shell.querySelector('[data-nav-close]');

        const closeNav = () => {
            shell.classList.remove('nav-open');
            toggleButton?.setAttribute('aria-expanded', 'false');
        };

        const openNav = () => {
            shell.classList.add('nav-open');
            toggleButton?.setAttribute('aria-expanded', 'true');
        };

        toggleButton?.addEventListener('click', () => {
            if (shell.classList.contains('nav-open')) {
                closeNav();
                return;
            }

            openNav();
        });

        closeButton?.addEventListener('click', closeNav);
        overlay?.addEventListener('click', closeNav);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeNav();
        });
    })();
</script>
@stack('scripts')
</body>
</html>
