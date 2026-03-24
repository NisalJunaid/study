@extends('layouts.student', ['heading' => 'Student Dashboard', 'subheading' => 'Start today\'s revision with confidence.'])

@section('content')
<div class="page-hero">
    <h2 style="margin:0">Welcome back 👋</h2>
    <p style="margin:.5rem 0 0;opacity:.9">Pick a subject, start a quiz, and monitor weak areas over time.</p>
</div>
<div class="card-grid">
    <article class="card"><h3>Active subjects</h3><p class="muted">5 available</p></article>
    <article class="card"><h3>Quiz streak</h3><p class="muted">4 days</p></article>
    <article class="card"><h3>Average score</h3><p class="muted">72%</p></article>
</div>
@endsection
