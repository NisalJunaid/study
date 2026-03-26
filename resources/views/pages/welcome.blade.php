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
        $secondaryCtaLabel = 'See how it works';
        $secondaryCtaRoute = '#how-it-works';
    } elseif ($user->isAdmin()) {
        $primaryCtaLabel = 'Admin Dashboard';
        $primaryCtaRoute = route('admin.dashboard');
        $secondaryCtaLabel = 'See how it works';
        $secondaryCtaRoute = '#how-it-works';
    } else {
        $primaryCtaLabel = 'Build Quiz';
        $primaryCtaRoute = route('student.quiz.setup');
        $secondaryCtaLabel = 'See how it works';
        $secondaryCtaRoute = '#how-it-works';
    }

    $levelModules = [
        \App\Models\Subject::LEVEL_O => [
            'label' => \App\Models\Subject::levelLabel(\App\Models\Subject::LEVEL_O),
            'headline' => 'Core exam foundations',
            'accent' => '#4f46e5',
        ],
        \App\Models\Subject::LEVEL_A => [
            'label' => \App\Models\Subject::levelLabel(\App\Models\Subject::LEVEL_A),
            'headline' => 'Advanced exam depth',
            'accent' => '#0ea5e9',
        ],
    ];
@endphp
<body class="focus-home-page">
    <div class="focus-home-bg"></div>
    <main class="focus-home-shell">
        <header class="focus-home-nav-wrap">
            <nav class="focus-home-nav" aria-label="Primary">
                <a href="{{ route('home') }}" class="focus-home-brand">
                    <span class="focus-home-brand-badge">F</span>
                    <span>Focus Lab</span>
                </a>

                <button class="focus-home-menu-toggle" type="button" data-home-menu-toggle aria-expanded="false" aria-controls="home-nav-links">
                    Menu
                </button>

                <div class="focus-home-links" id="home-nav-links" data-home-nav-links>
                    <a href="{{ route('home') }}">Home</a>
                    <a href="#coverage">Coverage</a>
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How it works</a>
                    <a href="#pricing">Pricing</a>
                </div>

                <div class="focus-home-actions">
                    <button class="focus-home-theme-toggle" type="button" data-theme-toggle-public aria-label="Toggle theme">🌗</button>
                    @guest
                        <a href="{{ route('login') }}" class="focus-home-login-link">Log in</a>
                    @else
                        <a href="{{ $user->isAdmin() ? route('admin.dashboard') : route('student.dashboard') }}" class="focus-home-login-link">Dashboard</a>
                    @endguest
                    <a href="{{ $primaryCtaRoute }}" class="focus-home-btn focus-home-btn-primary">{{ $primaryCtaLabel }}</a>
                </div>
            </nav>
        </header>

        <section class="focus-home-hero" id="how-it-works">
            <article class="focus-home-hero-copy">
                <span class="focus-home-chip">● O’Level & A’Level support added</span>
                <h1>Master your exams with <span>guided practice.</span></h1>
                <p>
                    Focus Lab gives you step-by-step, subject-based quiz preparation built for O’Level and A’Level learners.
                    Stay focused, build confidence, and improve one quiz at a time.
                </p>
                <div class="focus-home-hero-actions">
                    <a href="{{ $primaryCtaRoute }}" class="focus-home-btn focus-home-btn-primary">{{ $primaryCtaLabel }} →</a>
                    <a href="{{ $secondaryCtaRoute }}" class="focus-home-btn">{{ $secondaryCtaLabel }}</a>
                </div>
                <div class="focus-home-trust-strip">
                    <div class="focus-home-avatars" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>
                    <p><strong>4.9/5</strong> from students using Focus Lab weekly</p>
                </div>
            </article>

            <aside class="focus-home-hero-preview" aria-label="Quiz Builder preview">
                <div class="focus-home-preview-window">
                    <div class="focus-home-preview-top">
                        <span></span><span></span><span></span>
                        <small>Quiz Builder</small>
                    </div>
                    <p class="focus-home-preview-step">Step 2 of 4</p>
                    <div class="focus-home-preview-progress"><span></span></div>
                    <h2>What do you want to practice?</h2>
                    <div class="focus-home-preview-option is-active">
                        <div>
                            <strong>Mathematics</strong>
                            <small>Core & Extended</small>
                        </div>
                        <span>✓</span>
                    </div>
                    <div class="focus-home-preview-option">
                        <div>
                            <strong>Physics</strong>
                            <small>Mechanics & Thermal</small>
                        </div>
                        <span></span>
                    </div>
                    <a href="{{ $primaryCtaRoute }}" class="focus-home-preview-cta">Continue</a>
                </div>
                <div class="focus-home-preview-score">Score 96% <small>Last quiz</small></div>
            </aside>
        </section>

        <section class="focus-home-coverage" id="coverage">
            <div class="focus-home-coverage-head">
                <p class="focus-home-section-label">Coverage at a glance</p>
                <h2>Supported levels, subjects, and practice style</h2>
                <p class="focus-home-section-copy">Build smart revision routines with broad subject coverage, mixed question formats, and guided exam pacing.</p>
            </div>

            <div class="focus-home-coverage-grid">
                <article class="focus-home-scope-card">
                    <header>
                        <h3>Supported Levels & Subjects</h3>
                        <p>Choose your exam level and revise across key subjects.</p>
                    </header>

                    <div class="focus-home-level-grid">
                        @foreach($levelModules as $levelKey => $module)
                            @php
                                $entry = $subjectsByLevel[$levelKey] ?? ['count' => 0, 'subjects' => []];
                                $accent = $module['accent'];
                            @endphp
                            <section
                                class="focus-home-level-card"
                                style="--level-accent: {{ $accent }}; --level-accent-soft: {{ \App\Models\Subject::colorToRgba($accent, 0.16) }};"
                            >
                                <p class="focus-home-level-badge">{{ $module['label'] }}</p>
                                <h4>{{ $module['headline'] }}</h4>
                                <p class="focus-home-level-meta">{{ $entry['count'] }} subjects available</p>

                                <div class="focus-home-subject-chips" aria-label="{{ $module['label'] }} subjects">
                                    @foreach($entry['subjects'] as $subject)
                                        @php
                                            $subjectColor = \App\Models\Subject::normalizeColor($subject['color'] ?? null, $accent);
                                        @endphp
                                        <span
                                            class="focus-home-subject-chip"
                                            style="--subject-accent: {{ $subjectColor }}; --subject-soft: {{ \App\Models\Subject::colorToRgba($subjectColor, 0.18) }};"
                                        >
                                            {{ $subject['name'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </article>

                <article class="focus-home-practice-card">
                    <header>
                        <h3>Question Types</h3>
                        <p>Practice both speed and written reasoning in one flow.</p>
                    </header>

                    <div class="focus-home-question-types">
                        <div class="focus-home-question-pill">
                            <span>☑</span>
                            <div>
                                <strong>MCQ Practice</strong>
                                <small>Fast recall and exam-style choices</small>
                            </div>
                        </div>
                        <div class="focus-home-question-pill">
                            <span>✎</span>
                            <div>
                                <strong>Theory Responses</strong>
                                <small>Structured written-answer preparation</small>
                            </div>
                        </div>
                        <div class="focus-home-question-pill">
                            <span>⇄</span>
                            <div>
                                <strong>Mixed Mode</strong>
                                <small>Balanced drills for complete readiness</small>
                            </div>
                        </div>
                    </div>

                    <div class="focus-home-pacing-card" aria-label="Exam pacing guidance">
                        <div class="focus-home-pacing-heading">
                            <h4>Practice With Pace</h4>
                            <span>Exam pacing guide</span>
                        </div>
                        <p>Aim for ideal timing per question to build speed, accuracy, and confidence under real exam pressure.</p>
                        <div class="focus-home-pacing-track" role="presentation">
                            <span style="width: 72%"></span>
                        </div>
                        <div class="focus-home-pacing-stats">
                            <small>Ideal: 01:45 / question</small>
                            <small>Current: 01:52 avg</small>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="focus-home-features" id="features">
            <p class="focus-home-section-label">Key benefits</p>
            <h2>Everything you need to excel</h2>
            <p class="focus-home-section-copy">Built for focused revision with clear steps, clean screens, and meaningful feedback after every quiz.</p>

            <div class="focus-home-feature-grid">
                <article class="focus-home-feature-card">
                    <span class="focus-home-feature-icon">✦</span>
                    <h3>Guided Quiz Builder</h3>
                    <p>Choose level, subject, and topics through a smooth step-by-step flow that keeps setup simple.</p>
                </article>
                <article class="focus-home-feature-card">
                    <span class="focus-home-feature-icon">◔</span>
                    <h3>Distraction-Free Interface</h3>
                    <p>Practice one question at a time in a focused workspace designed for calm, consistent progress.</p>
                </article>
                <article class="focus-home-feature-card">
                    <span class="focus-home-feature-icon">◕</span>
                    <h3>Clear Results & Feedback</h3>
                    <p>Review instant scores, explanations, and performance trends so you know exactly what to improve.</p>
                </article>
            </div>
        </section>

        <footer class="focus-home-footer" id="pricing">
            <div>
                <a href="{{ route('home') }}" class="focus-home-brand">
                    <span class="focus-home-brand-badge">F</span>
                    <span>Focus Lab</span>
                </a>
                <p>Modern exam preparation for O’Level and A’Level students.</p>
                <small>© {{ now()->year }} Focus Lab. All rights reserved.</small>
            </div>

            <div class="focus-home-footer-links">
                <div>
                    <h4>Platform</h4>
                    <a href="#features">Features</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#coverage">Subjects</a>
                </div>
                <div>
                    <h4>Support</h4>
                    <a href="{{ route('login') }}">Help Center</a>
                    <a href="{{ route('register') }}">Contact Us</a>
                    <a href="{{ route('home') }}">Terms of Service</a>
                </div>
            </div>
        </footer>
    </main>

    <script>
        (() => {
            const menuToggle = document.querySelector('[data-home-menu-toggle]');
            const navLinks = document.querySelector('[data-home-nav-links]');
            const themeToggle = document.querySelector('[data-theme-toggle-public]');
            const storageKey = 'focus-lab-theme';

            menuToggle?.addEventListener('click', () => {
                const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
                menuToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                navLinks?.classList.toggle('is-open', !expanded);
            });

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
