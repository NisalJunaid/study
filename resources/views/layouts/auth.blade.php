<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Focus Lab' }}</title>
    <script>
        (() => {
            const storageKey = 'focus-lab-theme';
            const preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const saved = localStorage.getItem(storageKey);
            document.documentElement.dataset.theme = saved === 'dark' || saved === 'light' ? saved : preferred;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="focus-auth-page">
    <div class="focus-auth-bg" aria-hidden="true"></div>

    <main class="focus-auth-shell {{ $shellClass ?? '' }}" @if(!empty($mainId)) id="{{ $mainId }}" @endif>
        <aside class="focus-auth-aside card">
            <div class="focus-auth-aside-head">
                <a href="{{ route('home') }}" class="focus-home-brand focus-auth-brand" aria-label="Go to Focus Lab homepage">
                    <span class="focus-home-brand-badge">F</span>
                    <span>Focus Lab</span>
                </a>
                <button class="focus-home-theme-toggle" type="button" data-theme-toggle-auth aria-label="Toggle theme">🌗</button>
            </div>

            <p class="pill">Focus Lab</p>
            <h1 class="h1">{{ $heroTitle ?? 'Guided exam prep that keeps you on track.' }}</h1>
            <p class="muted mb-0">{{ $heroCopy ?? "Build confidence with a clean, focused workspace designed for O'Level and A'Level success." }}</p>

            <div class="focus-auth-aside-points">
                <p><span>✓</span> Guided quiz creation and structured progress</p>
                <p><span>✓</span> Real-time feedback and clear performance summaries</p>
                <p><span>✓</span> Calm design in both light and dark mode</p>
            </div>
        </aside>

        <section class="focus-auth-content card">
            @yield('content')
        </section>
    </main>

    <script>
        (() => {
            const themeToggle = document.querySelector('[data-theme-toggle-auth]');
            const storageKey = 'focus-lab-theme';

            themeToggle?.addEventListener('click', () => {
                const current = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.dataset.theme = next;
                localStorage.setItem(storageKey, next);
            });
        })();
    </script>
</body>
</html>
