<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Focus Lab | Guided O'Level & A'Level Study Help</title>
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
                <span class="pill">Focus Lab • Guided Exam Prep</span>
                <h1 class="h0">O'Level & A'Level revision that stays clear, structured, and motivating.</h1>
                <p class="mb-0 muted" style="max-width: 64ch;">Focus Lab helps students build guided quizzes by subject and topic, practice in manageable steps, and track weak areas over time.</p>
            </div>
            <div class="actions-inline">
                <a href="{{ $primaryCtaRoute }}" class="btn btn-primary">{{ $primaryCtaLabel }}</a>
                <a href="{{ $secondaryCtaRoute }}" class="btn">{{ $secondaryCtaLabel }}</a>
            </div>
        </header>

        <section class="landing-hero">
            <article class="landing-surface stack-md">
                <h2 class="h1">Learn smarter with guided quiz-based practice</h2>
                <p class="mb-0">Choose your level, pick subjects, focus topics, and practice in timed or untimed modes. Students start free, then continue through a flexible subscription model with quick payment verification.</p>
                <div class="actions-inline" style="justify-content:flex-start;">
                    <a href="{{ $primaryCtaRoute }}" class="btn btn-primary">{{ $primaryCtaLabel }}</a>
                    <a href="{{ $secondaryCtaRoute }}" class="btn">{{ $secondaryCtaLabel }}</a>
                </div>
                <div class="landing-grid-3">
                    <div class="summary-tile"><p class="h2 mb-0">Guided Flow</p><p class="mb-0 muted text-sm">Step-by-step quiz setup with clean progress cues.</p></div>
                    <div class="summary-tile"><p class="h2 mb-0">O/A Level Support</p><p class="mb-0 muted text-sm">Built for exam-focused subject and topic mastery.</p></div>
                    <div class="summary-tile"><p class="h2 mb-0">Free Trial + Plans</p><p class="mb-0 muted text-sm">Start free, then continue with affordable subscriptions.</p></div>
                </div>
            </article>
            <aside class="landing-surface stack-sm">
                <div class="landing-placeholder">Hero Illustration Placeholder<br><span class="text-sm muted">Replace with student learning visual</span></div>
                <p class="mb-0 muted text-sm">Designed for students who need a focused and reliable revision rhythm without a cluttered interface.</p>
            </aside>
        </section>

        <section class="landing-surface stack-sm">
            <h2 class="h1">How Focus Lab works</h2>
            <div class="landing-steps">
                <article class="landing-step"><p class="pill">Step 1</p><h3 class="h3">Pick your level and subject</h3><p class="mb-0 muted text-sm">Start with O'Level or A'Level context and choose where to focus.</p></article>
                <article class="landing-step"><p class="pill">Step 2</p><h3 class="h3">Build a guided quiz</h3><p class="mb-0 muted text-sm">Set question count, mode, and optional topic filters.</p></article>
                <article class="landing-step"><p class="pill">Step 3</p><h3 class="h3">Practice and review</h3><p class="mb-0 muted text-sm">Submit answers and get clear feedback on strengths and weak areas.</p></article>
                <article class="landing-step"><p class="pill">Step 4</p><h3 class="h3">Track progress</h3><p class="mb-0 muted text-sm">Use history and performance views to plan your next study session.</p></article>
            </div>
        </section>

        <section class="landing-grid-2">
            <article class="landing-surface stack-sm">
                <h2 class="h1">Reviews from learners</h2>
                <div class="stack-sm">
                    <div class="summary-tile"><p class="mb-0">“The guided flow helped me stop guessing and start practicing weak topics daily.”</p><p class="mb-0 muted text-sm">— Sarah, O'Level student</p></div>
                    <div class="summary-tile"><p class="mb-0">“I like that I can build quick mixed quizzes before class tests.”</p><p class="mb-0 muted text-sm">— Ibrahim, A'Level student</p></div>
                    <div class="summary-tile"><p class="mb-0">“Progress and quiz history made revision planning simple and realistic.”</p><p class="mb-0 muted text-sm">— Blessing, O'Level student</p></div>
                </div>
            </article>
            <article class="landing-surface stack-sm">
                <h2 class="h1">Announcements & highlights</h2>
                <div class="stack-sm">
                    <div class="summary-tile"><p class="h3 mb-0">New: topic-focused quiz setup</p><p class="mb-0 muted text-sm">Students can now target specific weak topics faster.</p></div>
                    <div class="summary-tile"><p class="h3 mb-0">Improved billing journey</p><p class="mb-0 muted text-sm">Cleaner payment flow with instant temporary access while verification is pending.</p></div>
                    <div class="summary-tile"><p class="h3 mb-0">Upcoming: richer progress insights</p><p class="mb-0 muted text-sm">More guidance for revision planning by topic and performance trend.</p></div>
                </div>
                <div class="landing-placeholder">Highlights Image Placeholder</div>
            </article>
        </section>

        <section class="landing-surface stack-sm">
            <h2 class="h1">Start free, then continue with subscription support</h2>
            <p class="mb-0 muted">Every student gets a free trial to test the guided workflow. When ready, choose a subscription plan, upload payment proof, and continue practice while verification is processed.</p>
            <div class="actions-inline" style="justify-content:flex-start;">
                <a href="{{ $primaryCtaRoute }}" class="btn btn-primary">{{ $primaryCtaLabel }}</a>
                <a href="{{ $secondaryCtaRoute }}" class="btn">{{ $secondaryCtaLabel }}</a>
            </div>
        </section>

        <footer class="landing-surface landing-footer">
            <p class="mb-0">© {{ now()->year }} Focus Lab • Study support for O'Level and A'Level learners.</p>
            <div class="actions-inline">
                <a class="btn btn-ghost" href="{{ route('home') }}">Home</a>
                <a class="btn btn-ghost" href="{{ $secondaryCtaRoute }}">Account</a>
            </div>
        </footer>
    </main>
</body>
</html>
