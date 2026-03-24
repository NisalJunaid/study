@extends('layouts.student', ['heading' => 'Choose Your Level', 'subheading' => 'Start with level, then pick a subject and optional topics.'])

@section('content')
<div class="stack-lg">
    <section class="page-hero">
        <h2 class="h1">Step 1: Select Level</h2>
        <p class="mb-0" style="opacity:.92">Pick your academic level to see relevant subjects only.</p>
    </section>

    <section class="card-grid">
        @foreach($levels as $level)
            <a class="card stack-md level-card" href="{{ route('student.levels.subjects.index', ['level' => $level['value']]) }}">
                <div class="row-between">
                    <h3 class="h2">{{ $level['label'] }}</h3>
                    <span class="pill">Step 2</span>
                </div>
                <p class="muted mb-0">{{ $level['subjects_count'] }} subject(s) available</p>
                <p class="muted mb-0">{{ $level['questions_count'] }} published question(s)</p>
                <span class="btn btn-primary">Continue</span>
            </a>
        @endforeach
    </section>
</div>
@endsection
