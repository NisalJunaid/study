@extends('layouts.student', ['heading' => 'Select a Subject', 'subheading' => "Step 2 for {$levelLabel}: choose a subject to continue."])

@section('content')
<div class="stack-lg">
    <section class="row-between card section-surface-secondary">
        <div class="stack-sm">
            <span class="pill">{{ $levelLabel }}</span>
            <h2 class="section-heading step-heading"><span class="step-index">2</span><span>Select a subject</span></h2>
            <p class="section-intro">Only subjects in this level are shown.</p>
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
                @php
                    $subjectColor = \App\Models\Subject::normalizeColor($subject->color);
                @endphp
                <article class="card stack-md subject-card" style="--subject-accent: {{ $subjectColor }}; --subject-tint: {{ \App\Models\Subject::colorToRgba($subjectColor, 0.18) }};">
                    <div class="row-between">
                        <h3 class="h2 row-wrap"><span class="subject-color-dot" aria-hidden="true"></span>{{ $subject->name }}</h3>
                        <span class="pill">{{ $subject->available_questions_count }} questions</span>
                    </div>

                    <p class="muted mb-0">{{ $subject->description ?: 'Focused exam practice by topic and difficulty.' }}</p>

                    <p class="muted text-sm mb-0">{{ $subject->active_topics_count }} topics · {{ $subject->mcq_questions_count }} MCQ · {{ $subject->theory_questions_count }} theory</p>

                    <a class="btn btn-primary" href="{{ route('student.quiz.setup', ['level' => $level, 'subject_id' => $subject->id]) }}">
                        Choose {{ $subject->name }}
                    </a>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
