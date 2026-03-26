<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Focus Lab | Guided O'Level & A'Level Study Help</title>
    <script>
        (() => {
            const storageKey = 'focus-lab-theme';
            const root = document.documentElement;
            const preferred = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const saved = localStorage.getItem(storageKey);
            root.dataset.theme = saved === 'dark' || saved === 'light' ? saved : preferred;
        })();
    </script>
    @vite(['resources/css/app.css'])
</head>
@php
    $user = auth()->user();

    if (! $user) {
        $primaryCtaLabel = 'Get Started';
        $primaryCtaRoute = route('register');
        $secondaryCtaLabel = 'Log In';
        $secondaryCtaRoute = route('login');
    } elseif ($user->isAdmin()) {
        $primaryCtaLabel = 'Open Admin Dashboard';
        $primaryCtaRoute = route('admin.dashboard');
        $secondaryCtaLabel = 'Manage Billing';
        $secondaryCtaRoute = route('admin.billing.payments.index');
    } else {
        $primaryCtaLabel = 'Build Quiz';
        $primaryCtaRoute = route('student.quiz.setup');
        $secondaryCtaLabel = 'View Progress';
        $secondaryCtaRoute = route('student.progress.index');
    }
@endphp
<body class="landing-page">
    <main class="landing-shell">
        <header class="landing-surface row-between" style="align-items:flex-start;">
            <div class="stack-sm">
                <span class="pill">Focus Lab</span>
                <h1 class="h0">Focused O'Level & A'Level prep.</h1>
            </div>
            <div class="actions-inline">
                <a href="{{ $primaryCtaRoute }}" class="btn btn-primary">{{ $primaryCtaLabel }}</a>
                <a href="{{ $secondaryCtaRoute }}" class="btn">{{ $secondaryCtaLabel }}</a>
            </div>
        </header>

        <section class="landing-hero">
            <article class="landing-surface stack-md">
                <h2 class="h1">Smart quiz prep</h2>
                <div class="actions-inline" style="justify-content:flex-start;">
                    <a href="{{ $primaryCtaRoute }}" class="btn btn-primary">{{ $primaryCtaLabel }}</a>
                    <a href="{{ $secondaryCtaRoute }}" class="btn">{{ $secondaryCtaLabel }}</a>
                </div>
                <div class="landing-grid-3">
                    <div class="summary-tile"><p class="h2 mb-0">Guided setup</p></div>
                    <div class="summary-tile"><p class="h2 mb-0">Topic focus</p></div>
                    <div class="summary-tile"><p class="h2 mb-0">Progress tracking</p></div>
                </div>
            </article>
            <aside class="landing-surface stack-sm">
                <div class="landing-placeholder">Hero Illustration</div>
            </aside>
        </section>

        <section class="landing-surface stack-sm">
            <h2 class="h1">How it works</h2>
            <div class="landing-steps">
                <article class="landing-step"><p class="pill">Step 1</p><h3 class="h3">Choose level</h3></article>
                <article class="landing-step"><p class="pill">Step 2</p><h3 class="h3">Build quiz</h3></article>
                <article class="landing-step"><p class="pill">Step 3</p><h3 class="h3">Submit answers</h3></article>
                <article class="landing-step"><p class="pill">Step 4</p><h3 class="h3">Review results</h3></article>
            </div>
        </section>

        <section class="landing-grid-2">
            <article class="landing-surface stack-sm">
                <h2 class="h1">For students</h2>
                <div class="stack-sm">
                    <div class="summary-tile"><p class="mb-0">Subject and topic-based practice</p></div>
                    <div class="summary-tile"><p class="mb-0">MCQ, theory, and mixed quizzes</p></div>
                    <div class="summary-tile"><p class="mb-0">Clear progress history</p></div>
                </div>
            </article>
            <article class="landing-surface stack-sm">
                <h2 class="h1">Highlights</h2>
                <div class="stack-sm">
                    <div class="summary-tile"><p class="h3 mb-0">Guided quiz builder</p></div>
                    <div class="summary-tile"><p class="h3 mb-0">Fast billing flow</p></div>
                    <div class="summary-tile"><p class="h3 mb-0">Realtime updates</p></div>
                </div>
                <div class="landing-placeholder">Highlights</div>
            </article>
        </section>

        <section class="landing-surface stack-sm">
            <h2 class="h1">Start now</h2>
            <div class="actions-inline" style="justify-content:flex-start;">
                <a href="{{ $primaryCtaRoute }}" class="btn btn-primary">{{ $primaryCtaLabel }}</a>
                <a href="{{ $secondaryCtaRoute }}" class="btn">{{ $secondaryCtaLabel }}</a>
            </div>
        </section>

        <footer class="landing-surface landing-footer">
            <p class="mb-0">© {{ now()->year }} Focus Lab • O'Level & A'Level study support.</p>
            <div class="actions-inline">
                <a class="btn btn-ghost" href="{{ route('home') }}">Home</a>
                <a class="btn btn-ghost" href="{{ $secondaryCtaRoute }}">Account</a>
            </div>
        </footer>
    </main>
</body>
</html>
