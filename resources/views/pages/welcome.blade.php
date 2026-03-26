<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Focus Lab | O'Level Study Help</title>
    @vite(['resources/css/app.css'])
</head>
<body class="centered-screen">
    <main class="card centered-shell stack-lg" style="padding: clamp(1rem, 3vw, 2rem);">
        <header class="row-between" style="align-items:flex-start;">
            <div class="stack-sm">
                <span class="pill">O'Level Study Help</span>
                <h1 class="h0">Focus Lab</h1>
            </div>
            <div class="actions-inline">
                @guest
                    <a href="{{ route('login') }}" class="btn">Login</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary">Sign Up</a>
                    @endif
                @else
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Admin Dashboard</a>
                    @else
                        <a href="{{ route('student.quiz.setup') }}" class="btn btn-primary">Build Quiz</a>
                    @endif
                @endguest
            </div>
        </header>

        <section class="page-hero stack-sm">
            <h2 class="h1">Learn smarter. Revise faster. Score higher.</h2>
            <p class="mb-0" style="max-width: 56ch;">
                Build focused quizzes by level, subject, and topic. Track your progress, improve weak areas, and stay exam-ready with a clean guided experience.
            </p>
        </section>

        <section class="row-between" style="align-items:center; gap:1rem; flex-wrap:wrap;">
            <p class="muted mb-0">Start with a guided setup and keep your practice consistent.</p>
            @guest
                <a href="{{ route('register') }}" class="btn btn-primary">Get Started</a>
            @else
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to Admin</a>
                @else
                    <a href="{{ route('student.quiz.setup') }}" class="btn btn-primary">Build Quiz</a>
                @endif
            @endguest
        </section>
    </main>
</body>
</html>
