@extends('layouts.student', ['heading' => 'Quiz In Progress', 'subheading' => 'Your quiz has been generated with a fixed question snapshot.'])

@section('content')
<div class="stack-lg">
    <section class="card">
        <div class="row-between">
            <h3 style="margin:0">{{ $quiz->subject?->name ?? 'General quiz' }}</h3>
            <span class="pill">{{ strtoupper($quiz->mode) }}</span>
        </div>
        <p class="muted">{{ $quiz->total_questions }} questions • Total marks: {{ $quiz->total_possible_score }}</p>
    </section>

    @if($quiz->quizQuestions->isEmpty())
        <section class="empty-state">
            <h4>No quiz questions assigned</h4>
            <p class="muted">This quiz could not be initialized. Please return to the quiz builder.</p>
        </section>
    @else
        <section class="stack-md">
            @foreach($quiz->quizQuestions as $quizQuestion)
                <article class="card">
                    <div class="row-between">
                        <strong>Question {{ $quizQuestion->order_no }}</strong>
                        <span class="pill">{{ strtoupper($quizQuestion->question_snapshot['type']) }}</span>
                    </div>
                    <p>{{ $quizQuestion->question_snapshot['question_text'] }}</p>

                    @if(($quizQuestion->question_snapshot['type'] ?? null) === 'mcq')
                        <ul class="muted" style="margin:0;padding-left:1rem;display:grid;gap:.3rem">
                            @foreach($quizQuestion->question_snapshot['options'] ?? [] as $option)
                                <li><strong>{{ $option['option_key'] }}.</strong> {{ $option['option_text'] }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="card card-soft">
                            <p class="muted" style="margin:0">Theory answer input flow will continue in the next implementation step.</p>
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
