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

        <section class="focus-home-subjects" id="coverage">
            <div class="focus-home-subjects-head">
                <p class="focus-home-section-label">Subjects</p>
                <h2>Pick your level, then revise by subject</h2>
                <p class="focus-home-section-copy">Switch between levels and browse supported subjects with quick, focused navigation.</p>
            </div>

            <div class="focus-home-level-tabs" role="tablist" aria-label="Select level">
                @foreach($levelModules as $levelKey => $module)
                    <button
                        type="button"
                        class="focus-home-level-tab {{ $loop->first ? 'is-active' : '' }}"
                        role="tab"
                        id="level-tab-{{ $levelKey }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="level-panel-{{ $levelKey }}"
                        data-level-tab
                        data-level="{{ $levelKey }}"
                    >
                        {{ $module['label'] }}
                    </button>
                @endforeach
            </div>

            @foreach($levelModules as $levelKey => $module)
                @php
                    $entry = $subjectsByLevel[$levelKey] ?? ['count' => 0, 'subjects' => []];
                    $accent = $module['accent'];
                @endphp
                <div
                    class="focus-home-level-panel {{ $loop->first ? 'is-active' : '' }}"
                    id="level-panel-{{ $levelKey }}"
                    role="tabpanel"
                    aria-labelledby="level-tab-{{ $levelKey }}"
                    data-level-panel
                    data-level="{{ $levelKey }}"
                >
                    <div class="focus-home-level-panel-head">
                        <div>
                            <h3>{{ $module['headline'] }}</h3>
                            <p>{{ $entry['count'] }} subjects available</p>
                        </div>
                        <div class="focus-home-carousel-controls">
                            <button type="button" class="focus-home-carousel-btn" data-carousel-prev aria-label="Previous subjects">←</button>
                            <button type="button" class="focus-home-carousel-btn" data-carousel-next aria-label="Next subjects">→</button>
                        </div>
                    </div>

                    <div class="focus-home-subject-carousel" data-carousel-track aria-label="{{ $module['label'] }} subjects">
                        @foreach($entry['subjects'] as $subject)
                            @php
                                $subjectColor = \App\Models\Subject::normalizeColor($subject['color'] ?? null, $accent);
                            @endphp
                            <article
                                class="focus-home-subject-card"
                                style="--subject-accent: {{ $subjectColor }}; --subject-soft: {{ \App\Models\Subject::colorToRgba($subjectColor, 0.18) }};"
                            >
                                <span class="focus-home-subject-dot" aria-hidden="true"></span>
                                <p>{{ $subject['name'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <section class="focus-home-question-types-row">
            <div class="focus-home-question-types-head">
                <p class="focus-home-section-label">Question types</p>
                <h2>Train with the mode you need</h2>
            </div>

            <div class="focus-home-question-type-grid">
                <article class="focus-home-question-type-card">
                    <span>☑</span>
                    <h3>MCQ</h3>
                    <p>Fast checks for exam-style recall.</p>
                </article>
                <article class="focus-home-question-type-card">
                    <span>✎</span>
                    <h3>Theory</h3>
                    <p>Structured written answers with feedback.</p>
                </article>
                <article class="focus-home-question-type-card">
                    <span>⇄</span>
                    <h3>Mixed</h3>
                    <p>Balanced drills across both formats.</p>
                </article>
            </div>
        </section>

        <section class="focus-home-pacing-row" aria-label="Exam pacing guidance">
            <div class="focus-home-pacing-copy">
                <p class="focus-home-section-label">Exam pacing</p>
                <h2>Practice with ideal timing</h2>
                <p>Build speed, time awareness, and confidence before exam day.</p>
            </div>
            <div class="focus-home-pacing-visual">
                <div class="focus-home-pacing-timer">
                    <strong>01:45</strong>
                    <small>Ideal per question</small>
                </div>
                <div class="focus-home-pacing-track" role="presentation">
                    <span style="width: 74%"></span>
                </div>
                <div class="focus-home-pacing-stats">
                    <small>Current pace: 01:52 avg</small>
                    <small>Target confidence: +18%</small>
                </div>
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

            const levelTabs = document.querySelectorAll('[data-level-tab]');
            const levelPanels = document.querySelectorAll('[data-level-panel]');

            const activateLevel = (level) => {
                levelTabs.forEach((tab) => {
                    const isActive = tab.dataset.level === level;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                levelPanels.forEach((panel) => {
                    const isActive = panel.dataset.level === level;
                    panel.classList.toggle('is-active', isActive);
                });
            };

            levelTabs.forEach((tab) => {
                tab.addEventListener('click', () => activateLevel(tab.dataset.level));
            });

            document.querySelectorAll('[data-level-panel]').forEach((panel) => {
                const track = panel.querySelector('[data-carousel-track]');
                const prev = panel.querySelector('[data-carousel-prev]');
                const next = panel.querySelector('[data-carousel-next]');

                prev?.addEventListener('click', () => {
                    track?.scrollBy({ left: -220, behavior: 'smooth' });
                });

                next?.addEventListener('click', () => {
                    track?.scrollBy({ left: 220, behavior: 'smooth' });
                });
            });
        })();
    </script>
</body>
</html>
