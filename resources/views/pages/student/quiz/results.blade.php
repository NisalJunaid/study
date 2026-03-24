@extends('layouts.student', ['heading' => 'Quiz Results', 'subheading' => 'Review your submitted answers and current grading status.'])

@section('content')
<div class="stack-lg">
    <section class="card">
        <div class="row-between">
            <h3 style="margin:0">{{ $quiz->subject?->name ?? 'General quiz' }}</h3>
            <span class="pill">{{ strtoupper($quiz->status) }}</span>
        </div>
        <p class="muted" style="margin-bottom:0">
            Score: {{ $quiz->total_awarded_score ?? '0.00' }} / {{ $quiz->total_possible_score }} •
            Submitted: {{ optional($quiz->submitted_at)->format('M d, Y H:i') ?? 'N/A' }}
        </p>

        @if($quiz->status === \App\Models\Quiz::STATUS_GRADING)
            <p class="muted" style="margin-top:.75rem;margin-bottom:0;">
                Theory grading is still processing. Refresh this page shortly to see updated feedback.
            </p>
        @endif
    </section>

    <section class="stack-md">
        @foreach($quiz->quizQuestions as $quizQuestion)
            @php
                $snapshot = $quizQuestion->question_snapshot ?? [];
                $answer = $quizQuestion->studentAnswer;
                $aiParsed = data_get($answer?->ai_result_json, 'parsed', []);
            @endphp
            <article class="card stack-md">
                <div class="row-between">
                    <strong>Question {{ $quizQuestion->order_no }} ({{ strtoupper($snapshot['type'] ?? '-') }})</strong>
                    <span class="pill">{{ $answer?->grading_status ?? 'pending' }}</span>
                </div>

                <p style="margin:0">{{ $snapshot['question_text'] ?? '' }}</p>

                @if(($snapshot['type'] ?? null) === 'mcq')
                    <p class="muted" style="margin:0">Selected option ID: {{ $answer?->selected_option_id ?? 'Not answered' }}</p>
                    <p class="muted" style="margin:0">Result: {{ $answer?->is_correct ? 'Correct' : 'Incorrect / Not graded' }}</p>
                @else
                    <p class="muted" style="white-space:pre-wrap;margin:0"><strong>Your answer:</strong> {{ $answer?->answer_text ?: 'No answer submitted.' }}</p>

                    @if(in_array($answer?->grading_status, [\App\Models\StudentAnswer::STATUS_PENDING, \App\Models\StudentAnswer::STATUS_PROCESSING], true))
                        <p class="muted" style="margin:0">Theory grading in progress…</p>
                    @else
                        <p class="muted" style="white-space:pre-wrap;margin:0"><strong>Feedback:</strong> {{ $answer?->feedback ?: 'No feedback yet.' }}</p>

                        @if(!empty($aiParsed))
                            <p class="muted" style="margin:0">
                                Verdict: {{ $aiParsed['verdict'] ?? 'N/A' }} ·
                                Confidence: {{ $aiParsed['confidence'] ?? 'N/A' }}
                            </p>
                        @endif
                    @endif
                @endif

                <p class="muted" style="margin:0">Score: {{ $answer?->score ?? 'Pending' }} / {{ $quizQuestion->max_score }}</p>
            </article>
        @endforeach
    </section>
</div>
@endsection
