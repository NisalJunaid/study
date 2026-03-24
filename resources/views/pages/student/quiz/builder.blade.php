@extends('layouts.student', ['heading' => 'Quiz Builder', 'subheading' => 'Create MCQ, theory, or mixed quizzes in a few steps.'])

@section('content')
<div class="card">
    <h3 style="margin-top:0">Quiz setup placeholder</h3>
    <p class="muted">This route is wired and protected. Full builder form logic will be added next.</p>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
        <span class="pill">Mode: Mixed</span>
        <span class="pill">Questions: 10</span>
        <span class="pill">Timed: Off</span>
    </div>
    <button class="btn btn-primary" style="margin-top:1rem">Start quiz</button>
</div>
@endsection
