@extends('layouts.student', ['heading' => 'Subjects', 'subheading' => 'Choose an active subject and launch focused quizzes by topic.'])

@section('content')
<div class="stack-lg">
    @if($subjects->isEmpty())
        <section class="empty-state">
            <h4>No active subjects yet</h4>
            <p class="muted">Your admin has not published any active subjects. Check back soon.</p>
        </section>
    @else
        <div class="card-grid">
            @foreach($subjects as $subject)
                <article class="card stack-md">
                    <div class="row-between">
                        <h3 style="margin:0">{{ $subject->name }}</h3>
                        @if($subject->color)
                            <span class="pill" style="background: {{ $subject->color }}22; color: {{ $subject->color }};">Active</span>
                        @else
                            <span class="pill">Active</span>
                        @endif
                    </div>

                    <p class="muted" style="margin:0">
                        {{ $subject->description ?: 'Practice this subject with MCQ, theory, and mixed quizzes.' }}
                    </p>

                    <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                        <span class="pill">{{ $subject->active_topics_count }} topics</span>
                        <span class="pill">{{ $subject->available_questions_count }} available questions</span>
                        <span class="pill">{{ $subject->mcq_questions_count }} MCQ</span>
                        <span class="pill">{{ $subject->theory_questions_count }} theory</span>
                    </div>

                    <div>
                        <a class="btn btn-primary" href="{{ route('student.subjects.show', $subject) }}">Open subject</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
