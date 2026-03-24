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
    <header class="shell-topbar">
        <button class="menu-trigger" type="button" aria-controls="app-sidebar" aria-expanded="false" data-nav-toggle aria-label="Open sidebar">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <rect x="3" y="5" width="5" height="14" rx="1.2"></rect>
                <path d="M11 7.5h10M11 12h10M11 16.5h10" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></path>
            </svg>
        </button>

        <a class="topbar-brand" href="{{ auth()->check() && auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard') }}">Focus Lab</a>

        @auth
            <div class="topbar-user-menu" data-user-menu>
                <button class="topbar-user-button" type="button" data-user-menu-toggle aria-expanded="false" aria-haspopup="true" aria-controls="user-menu-panel">
                    <span class="avatar-circle">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="user-name text-sm">{{ auth()->user()->name }}</span>
                    <span aria-hidden="true">▾</span>
                </button>

                <div class="user-dropdown card" id="user-menu-panel" data-user-menu-panel hidden>
                    <a href="{{ route('profile.edit') }}" class="user-dropdown-item">Profile</a>
                    <a href="{{ route('profile.settings') }}" class="user-dropdown-item">Settings</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="user-dropdown-item user-dropdown-item-danger">Sign out</button>
                    </form>
                </div>
            </div>
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
            <header class="topbar card {{ $contentWidthClass ?? '' }}">
                <div class="section-title">
                    <h1 class="h0">{{ $heading ?? 'Dashboard' }}</h1>
                    <p class="muted">{{ $subheading ?? 'Build momentum with focused practice.' }}</p>
                </div>
            </header>
        @endif

        @include('components.admin.flash')

        <section class="page-content {{ $contentWidthClass ?? '' }}">
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
        const navItems = shell.querySelectorAll('[data-student-nav] .nav-item');
        const userMenu = shell.querySelector('[data-user-menu]');
        const userMenuToggle = shell.querySelector('[data-user-menu-toggle]');
        const userMenuPanel = shell.querySelector('[data-user-menu-panel]');

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
        navItems.forEach((item) => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 1024) closeNav();
            });
        });

        userMenuToggle?.addEventListener('click', () => {
            const isOpen = userMenuToggle.getAttribute('aria-expanded') === 'true';
            userMenuToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            userMenuPanel.hidden = isOpen;
        });

        document.addEventListener('click', (event) => {
            if (!userMenu || userMenuPanel.hidden) return;
            if (!userMenu.contains(event.target)) {
                userMenuToggle?.setAttribute('aria-expanded', 'false');
                userMenuPanel.hidden = true;
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeNav();
                userMenuToggle?.setAttribute('aria-expanded', 'false');
                if (userMenuPanel) userMenuPanel.hidden = true;
            }
        });
    })();
</script>
@stack('scripts')
</body>
</html>
