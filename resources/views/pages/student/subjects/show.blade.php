@extends('layouts.student', ['heading' => $subject->name, 'subheading' => 'Review topics and available quiz modes before you start.'])

@section('content')
<div class="stack-lg">
    <section class="page-hero">
        <div class="row-between" style="align-items:flex-start">
            <div>
                <h3 class="h1">{{ $subject->name }}</h3>
                <p class="mb-0" style="opacity:.9">{{ $subject->description ?: 'Build confidence with targeted practice.' }}</p>
            </div>
            <a class="btn" href="{{ route('student.quiz.builder', ['subject_id' => $subject->id]) }}">Build quiz</a>
        </div>
    </section>

    <section class="card">
        <h3 class="h2 mt-0">Available quiz modes</h3>
        <div class="card-grid">
            @foreach($quizModes as $mode => $meta)
                <article class="card card-soft">
                    <h4 class="h3 mt-0">{{ $meta['label'] }}</h4>
                    <p class="muted text-sm">{{ $meta['count'] }} question(s) currently available.</p>
                    <a class="btn" href="{{ route('student.quiz.builder', ['subject_id' => $subject->id, 'mode' => $mode]) }}">Use {{ strtoupper($mode) }}</a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h3 class="h2 mt-0">Topics</h3>

        @if($subject->topics->isEmpty())
            <div class="empty-state">
                <h4>No active topics in this subject</h4>
                <p class="muted">You can still build quizzes from subject-level questions if available.</p>
            </div>
        @else
            <div class="card-grid">
                @foreach($subject->topics as $topic)
                    <article class="card card-soft">
                        <h4 class="h3 mt-0">{{ $topic->name }}</h4>
                        <p class="muted text-sm">{{ $topic->description ?: 'Topic practice available.' }}</p>
                        <div class="row-wrap">
                            <span class="pill">{{ $topic->available_questions_count }} total</span>
                            <span class="pill">{{ $topic->mcq_questions_count }} MCQ</span>
                            <span class="pill">{{ $topic->theory_questions_count }} theory</span>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
