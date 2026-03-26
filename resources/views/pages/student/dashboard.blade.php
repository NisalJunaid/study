@extends('layouts.student', ['heading' => 'Overview'])

@section('content')
<div class="actions-inline">
    <a class="btn btn-primary" href="{{ route('student.levels.index') }}">Start Quiz</a>
    <a class="btn" href="{{ route('student.history.index') }}">History</a>
</div>
<div class="card-grid">
    <article class="card"><h3 class="h2">Subjects</h3><p class="muted mb-0">5 available</p></article>
    <article class="card"><h3 class="h2">Quiz streak</h3><p class="muted mb-0">4 days</p></article>
    <article class="card"><h3 class="h2">Avg score</h3><p class="muted mb-0">72%</p></article>
</div>
@endsection
