@extends('layouts.student', [
    'heading' => 'Quiz',
        'minimalHeader' => true,
    'suppressFlash' => true,
])

@section('content')
@php
    $isLockedQuiz = in_array($quiz->status, [\App\Models\Quiz::STATUS_SUBMITTED, \App\Models\Quiz::STATUS_GRADING, \App\Models\Quiz::STATUS_GRADED], true);
    $questionPayload = $quiz->quizQuestions->map(function ($quizQuestion) {
        $snapshot = $quizQuestion->question_snapshot ?? [];
        $answer = $quizQuestion->studentAnswer;
        $idealTime = (int) ($snapshot['ideal_time_seconds'] ?? 90);

        return [
            'id' => $quizQuestion->id,
            'order_no' => $quizQuestion->order_no,
            'type' => $snapshot['type'] ?? null,
            'question_text' => $snapshot['question_text'] ?? '',
            'marks' => $quizQuestion->max_score,
            'ideal_time_seconds' => $idealTime,
            'options' => collect($snapshot['options'] ?? [])->map(fn ($option) => [
                'id' => $option['id'],
                'option_key' => $option['option_key'],
                'option_text' => $option['option_text'],
            ])->values()->all(),
            'structured_parts' => collect($snapshot['structured_parts'] ?? [])->map(fn ($part) => [
                'id' => $part['id'],
                'part_label' => $part['part_label'],
                'prompt_text' => $part['prompt_text'],
                'max_score' => $part['max_score'],
            ])->values()->all(),
            'answer' => [
                'selected_option_id' => $answer?->selected_option_id,
                'answer_text' => $answer?->answer_text,
                'answer_json' => $answer?->answer_json ?? [],
                'question_started_at' => optional($answer?->question_started_at)->toIso8601String(),
                'answered_at' => optional($answer?->answered_at)->toIso8601String(),
                'answer_duration_seconds' => $answer?->answer_duration_seconds,
                'ideal_time_seconds' => $answer?->ideal_time_seconds,
            ],
            'locked' => $answer?->answered_at !== null,
        ];
    })->values();
@endphp

<div class="quiz-minimal-wrap" id="quiz-take-app"
    data-questions='@json($questionPayload)'
    data-save-route-template="{{ route('student.quiz.answer.save', ['quiz' => $quiz, 'quizQuestion' => '__QUESTION__']) }}"
    data-interaction-route="{{ route('student.quiz.interact', $quiz) }}"
    data-csrf="{{ csrf_token() }}"
    data-locked="{{ $isLockedQuiz ? '1' : '0' }}"
>
    <section class="card stack-sm quiz-minimal-header">
        <div class="timer-panel">
            <div class="row-between">
                <strong id="question-counter">Question 1 of {{ $quiz->quizQuestions->count() }}</strong>
                <span class="pill" id="status-pill">Active</span>
            </div>
            <div class="timer-track progress-track">
                <div class="timer-fill progress-fill" id="question-timer-fill" style="width:0%"></div>
            </div>
            <div class="row-between text-xs muted">
                <span id="elapsed-time-text">Elapsed: 0s</span>
                <span id="ideal-time-text">Ideal: 0s</span>
            </div>
        </div>
        <div class="quiz-step-list" id="quiz-step-list"></div>
    </section>

    @if($quiz->quizQuestions->isEmpty())
        <section class="empty-state">
            <h4>No questions assigned</h4>
        </section>
    @else
        <section class="card stack-lg quiz-panel quiz-minimal-main" id="active-question-panel"></section>

        @if(! $isLockedQuiz)
            <form method="POST" action="{{ route('student.quiz.submit', $quiz) }}" id="submit-quiz-form" class="actions-row" style="justify-content:flex-end;display:none;">
                @csrf
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        @endif
    @endif
</div>

@endsection
