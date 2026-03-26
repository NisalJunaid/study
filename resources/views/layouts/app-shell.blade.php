<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Focus Lab' }}</title>
    <script>
        (() => {
            const storageKey = 'focus-lab-theme';
            const root = document.documentElement;
            const preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const saved = localStorage.getItem(storageKey);
            root.dataset.theme = saved === 'dark' || saved === 'light' ? saved : preferred;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php use App\Support\OverlayMessage; @endphp
    @php
        $activeSubscription = $activeSubscription ?? null;
        $showSuspensionOverlay = ($showSuspensionOverlay ?? false)
            && ! request()->routeIs('student.billing.*');
    @endphp
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
                    @if(!auth()->user()->isAdmin() && is_array($aiCredits ?? null))
                        <span class="pill" title="AI credits (available / total)">
                            {{ $aiCredits['available'] }} / {{ $aiCredits['total'] }}
                        </span>
                    @endif
                    <span aria-hidden="true">▾</span>
                </button>

                <div class="user-dropdown card" id="user-menu-panel" data-user-menu-panel hidden>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.billing.payments.index') }}" class="user-dropdown-item">Billing</a>
                    @else
                        <a href="{{ route('student.billing.subscription') }}" class="user-dropdown-item">Billing</a>
                    @endif
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

    <div class="sidebar-overlay" data-nav-overlay hidden></div>

    <aside class="sidebar" id="app-sidebar" data-sidebar>
        <div class="sidebar-top">
            <div class="sidebar-brand-row">
                <a class="sidebar-brand-link" href="{{ route('home') }}">
                    <span class="focus-home-brand-badge">F</span>
                    <span class="brand">Focus Lab</span>
                </a>
                <button class="mobile-nav-close" type="button" aria-label="Close navigation" data-nav-close>✕</button>
            </div>
            <div>
                @if(!empty($navDescription))
                    <p class="muted mt-0">{{ $navDescription }}</p>
                @endif
            </div>
        </div>

        @yield('sidebar')

        @auth
            <div class="sidebar-account card">
                <p class="text-sm muted mb-0">Account</p>
                <div class="sidebar-account-actions">
                    <a href="{{ route('profile.edit') }}" class="btn btn-ghost">Profile</a>
                    <a href="{{ route('profile.settings') }}" class="btn btn-ghost">Settings</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost">Sign out</button>
                    </form>
                </div>
            </div>
        @endauth

        <div class="sidebar-theme-toggle card" data-theme-control>
            <div>
                <p class="h3 mb-0">Theme</p>
                <p class="text-sm muted mb-0" data-theme-label>Light mode</p>
            </div>
            <label class="switch" aria-label="Toggle theme">
                <input type="checkbox" data-theme-toggle>
                <span class="switch-track"></span>
            </label>
        </div>
    </aside>

    <main class="main">
        @php
            $showHeader = $showHeader ?? !($minimalHeader ?? false);
            $resolvedHeading = $heading ?? null;
            $resolvedSubheading = $subheading ?? null;
        @endphp

        @if($showHeader && ($resolvedHeading || $resolvedSubheading))
            <header class="topbar card {{ $contentWidthClass ?? '' }}">
                <div class="section-title">
                    @if($resolvedHeading)
                        <h1 class="h0">{{ $resolvedHeading }}</h1>
                    @endif
                    @if($resolvedSubheading)
                        <p class="muted">{{ $resolvedSubheading }}</p>
                    @endif
                </div>
            </header>
        @endif

        @php
            $suppressFlashOverlay = (bool) ($suppressFlash ?? false);
            $rawOverlay = session('overlay');
            $overlayPayload = ! $suppressFlashOverlay && is_array($rawOverlay)
                ? OverlayMessage::renderableOrNull($rawOverlay)
                : null;
        @endphp

        <section class="page-content {{ $contentWidthClass ?? '' }}">
            @yield('content')
        </section>
    </main>
</div>

@if($showSuspensionOverlay)
    <div class="suspension-overlay" role="dialog" aria-modal="true" aria-label="Account suspended">
        <div class="suspension-overlay-card">
            <h2 class="h2">Account suspended</h2>
            <p class="mb-0">{{ $activeSubscription->suspended_reason ?: 'Account is suspended until payment is verified.' }}</p>
            <a class="btn btn-primary" href="{{ route('student.billing.subscription') }}">Open billing</a>
        </div>
    </div>
@endif

@php
    $initialOverlay = ! $showSuspensionOverlay
        ? OverlayMessage::renderableOrNull($overlayPayload)
        : null;
@endphp
<div class="global-overlay" data-global-overlay @if(!is_null($initialOverlay)) data-initial-overlay='@json($initialOverlay)' @endif hidden aria-hidden="true">
    <div class="global-overlay-card card" role="dialog" aria-modal="true" aria-live="assertive" aria-label="Notice">
        <button type="button" class="global-overlay-dismiss" data-overlay-dismiss aria-label="Close">✕</button>
        <div class="global-overlay-header">
            <span class="global-overlay-icon" data-overlay-icon aria-hidden="true">ℹ️</span>
            <h2 class="h2 mb-0 global-overlay-title" data-overlay-title></h2>
        </div>
        <p class="muted mb-0 global-overlay-message" data-overlay-message></p>
        <p class="text-sm muted mb-0 global-overlay-countdown" data-overlay-countdown hidden></p>
        <div class="actions-row global-overlay-actions">
            <button type="button" class="btn btn-ghost global-overlay-secondary" data-overlay-secondary hidden>Back</button>
            <button type="button" class="btn btn-primary global-overlay-primary" data-overlay-primary>OK</button>
        </div>
    </div>
</div>

<script>
    (() => {
        const shell = document.querySelector('[data-shell]');
        if (!shell) return;

        const overlay = shell.querySelector('[data-nav-overlay]');
        const toggleButton = shell.querySelector('[data-nav-toggle]');
        const closeButton = shell.querySelector('[data-nav-close]');
        const navItems = shell.querySelectorAll('[data-student-nav] .nav-item, [data-admin-nav] .nav-item');
        const userMenu = shell.querySelector('[data-user-menu]');
        const userMenuToggle = shell.querySelector('[data-user-menu-toggle]');
        const userMenuPanel = shell.querySelector('[data-user-menu-panel]');
        const themeToggle = shell.querySelector('[data-theme-toggle]');
        const themeLabel = shell.querySelector('[data-theme-label]');
        const storageKey = 'focus-lab-theme';

        const applyTheme = (theme) => {
            const resolved = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.dataset.theme = resolved;
            localStorage.setItem(storageKey, resolved);
            if (themeToggle) {
                themeToggle.checked = resolved === 'dark';
            }
            if (themeLabel) {
                themeLabel.textContent = resolved === 'dark' ? 'Dark mode' : 'Light mode';
            }
        };

        if (themeToggle) {
            const currentTheme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
            applyTheme(currentTheme);

            themeToggle.addEventListener('change', () => {
                applyTheme(themeToggle.checked ? 'dark' : 'light');
            });
        }

        const closeNav = () => {
            shell.classList.remove('nav-open');
            document.body.classList.remove('nav-lock');
            overlay?.setAttribute('hidden', 'hidden');
            toggleButton?.setAttribute('aria-expanded', 'false');
        };

        const openNav = () => {
            shell.classList.add('nav-open');
            document.body.classList.add('nav-lock');
            overlay?.removeAttribute('hidden');
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

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                closeNav();
            }
        });
    })();
</script>
@stack('scripts')
</body>
</html>
