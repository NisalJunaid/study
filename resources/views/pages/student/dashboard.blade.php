@extends('layouts.student', ['heading' => 'Student Dashboard', 'subheading' => 'Plan your revision and keep your progress moving.'])

@section('content')
<div class="page-hero">
    <h2 class="h1">Welcome back 👋</h2>
    <p class="mb-0" style="opacity:.92">Pick a subject, start a quiz, and monitor weak areas over time.</p>
    <div class="actions-inline" style="margin-top:.8rem">
        <a class="btn btn-primary" href="{{ route('student.levels.index') }}">Start Quiz Setup</a>
        <a class="btn" href="{{ route('student.history.index') }}">View History</a>
    </div>
</div>
<div class="card-grid">
    <article class="card"><h3 class="h2">Active subjects</h3><p class="muted mb-0">5 available</p></article>
    <article class="card"><h3 class="h2">Quiz streak</h3><p class="muted mb-0">4 days</p></article>
    <article class="card"><h3 class="h2">Average score</h3><p class="muted mb-0">72%</p></article>
</div>
@endsection
