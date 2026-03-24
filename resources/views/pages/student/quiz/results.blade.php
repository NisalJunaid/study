@extends('layouts.student', ['heading' => 'Quiz Results', 'subheading' => 'Review your answers, grading feedback, and next steps.'])

@section('content')
<div class="stack-lg">
    <section class="card">
        <div class="row-between">
            <div>
                <h3 style="margin:0">{{ $quiz->subject?->name ?? 'General quiz' }}</h3>
                <p class="muted" style="margin:.35rem 0 0">{{ strtoupper($quiz->mode) }} · {{ $quiz->total_questions }} question(s)</p>
            </div>
            <span class="pill {{ $quiz->status === \App\Models\Quiz::STATUS_GRADED ? 'pill-success' : 'pill-muted' }}">{{ strtoupper($quiz->status) }}</span>
        </div>

        <div class="result-summary-grid" style="margin-top:1rem">
            <div class="card-soft" style="padding:.75rem;border-radius:.75rem;">
                <p class="muted" style="margin:0">Score</p>
                <strong style="font-size:1.05rem;">{{ $quiz->total_awarded_score !== null ? number_format((float) $quiz->total_awarded_score, 2) : 'Pending' }} / {{ number_format((float) $quiz->total_possible_score, 2) }}</strong>
            </div>
            <div class="card-soft" style="padding:.75rem;border-radius:.75rem;">
                <p class="muted" style="margin:0">Submitted</p>
                <strong>{{ optional($quiz->submitted_at)->format('M d, Y H:i') ?? 'N/A' }}</strong>
            </div>
            <div class="card-soft" style="padding:.75rem;border-radius:.75rem;">
                <p class="muted" style="margin:0">Graded</p>
                <strong>{{ optional($quiz->graded_at)->format('M d, Y H:i') ?? 'In progress' }}</strong>
            </div>
        </div>

        @if($quiz->status === \App\Models\Quiz::STATUS_GRADING)
            <p class="muted" style="margin-top:.9rem;margin-bottom:0;">
                Theory grading is still running. You can refresh this page for updated scores and detailed feedback.
            </p>
        @endif
    </section>

    <section class="stack-md">
        @foreach($quiz->quizQuestions as $quizQuestion)
            @php
                $snapshot = $quizQuestion->question_snapshot ?? [];
                $answer = $quizQuestion->studentAnswer;
                $status = $answer?->grading_status ?? \App\Models\StudentAnswer::STATUS_PENDING;
                $aiParsed = data_get($answer?->ai_result_json, 'parsed', []);
            @endphp
            <article class="card stack-sm">
                <div class="row-between">
                    <strong>Question {{ $quizQuestion->order_no }} · {{ strtoupper($snapshot['type'] ?? '-') }}</strong>
                    <span class="pill {{ in_array($status, [\App\Models\StudentAnswer::STATUS_GRADED, \App\Models\StudentAnswer::STATUS_OVERRIDDEN], true) ? 'pill-success' : 'pill-muted' }}">{{ strtoupper($status) }}</span>
                </div>

                <p style="margin:0">{{ $snapshot['question_text'] ?? '' }}</p>

                @if(($snapshot['type'] ?? null) === \App\Models\Question::TYPE_MCQ)
                    <div class="stack-sm">
                        @foreach(($snapshot['options'] ?? []) as $option)
                            @php
                                $isSelected = (int) ($answer?->selected_option_id ?? 0) === (int) ($option['id'] ?? 0);
                                $isCorrect = (bool) ($option['is_correct'] ?? false);
                            @endphp
                            <div class="result-option {{ $isSelected ? 'selected' : '' }} {{ $isCorrect ? 'correct' : '' }}">
                                <span><strong>{{ $option['option_key'] ?? '?' }}.</strong> {{ $option['option_text'] ?? '' }}</span>
                                <span class="muted" style="font-size:.85rem;">
                                    {{ $isSelected ? 'Your choice' : '' }}
                                    {{ $isSelected && $isCorrect ? ' · Correct' : '' }}
                                    {{ $isSelected && ! $isCorrect ? ' · Incorrect' : '' }}
                                    {{ ! $isSelected && $isCorrect ? 'Correct answer' : '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <p class="muted" style="margin:0"><strong>Explanation:</strong> {{ $snapshot['explanation'] ?: 'No explanation provided.' }}</p>
                @else
                    <p class="muted" style="white-space:pre-wrap;margin:0"><strong>Your answer:</strong> {{ $answer?->answer_text ?: 'No answer submitted.' }}</p>

                    @if(in_array($status, [\App\Models\StudentAnswer::STATUS_PENDING, \App\Models\StudentAnswer::STATUS_PROCESSING], true))
                        <p class="muted" style="margin:0">Theory grading pending. Detailed feedback will appear once grading completes.</p>
                    @else
                        <p class="muted" style="white-space:pre-wrap;margin:0"><strong>Feedback:</strong> {{ $answer?->feedback ?: 'No feedback yet.' }}</p>
                        @if(!empty($aiParsed))
                            <p class="muted" style="margin:0">
                                Verdict: {{ strtoupper((string) ($aiParsed['verdict'] ?? 'n/a')) }}
                                · Confidence: {{ $aiParsed['confidence'] ?? 'N/A' }}
                            </p>
                        @endif
                    @endif
                @endif

                <p class="muted" style="margin:0"><strong>Score:</strong> {{ $answer?->score !== null ? number_format((float) $answer->score, 2) : 'Pending' }} / {{ number_format((float) $quizQuestion->max_score, 2) }}</p>
            </article>
        @endforeach
    </section>
</div>
@endsection
