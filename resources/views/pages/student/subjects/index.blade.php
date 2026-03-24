@extends('layouts.student', ['heading' => 'Select Subject', 'subheading' => "Step 2 for {$levelLabel}: choose one subject to continue."])

@section('content')
<div class="stack-lg">
    <section class="row-between">
        <div class="stack-sm">
            <span class="pill">{{ $levelLabel }}</span>
            <p class="muted mb-0">Only subjects in this level are shown.</p>
        </div>
        <a class="btn" href="{{ route('student.levels.index') }}">Change level</a>
    </section>

    @if($subjects->isEmpty())
        <section class="empty-state">
            <h4>No active subjects yet for {{ $levelLabel }}</h4>
            <p class="muted">Ask an admin to add subjects for this level.</p>
        </section>
    @else
        <section class="card-grid">
            @foreach($subjects as $subject)
                <article class="card stack-md subject-card" style="--subject-accent: {{ $subject->color ?: '#4f46e5' }};">
                    <div class="row-between">
                        <h3 class="h2">{{ $subject->name }}</h3>
                        <span class="pill">{{ $subject->available_questions_count }} questions</span>
                    </div>

                    <p class="muted mb-0">{{ $subject->description ?: 'Focused exam practice by topic and difficulty.' }}</p>

                    <div class="row-wrap">
                        <span class="pill">{{ $subject->active_topics_count }} topics</span>
                        <span class="pill">{{ $subject->mcq_questions_count }} MCQ</span>
                        <span class="pill">{{ $subject->theory_questions_count }} theory</span>
                    </div>

                    <a class="btn btn-primary" href="{{ route('student.quiz.setup', ['level' => $level, 'subject_id' => $subject->id]) }}">
                        Choose {{ $subject->name }}
                    </a>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
