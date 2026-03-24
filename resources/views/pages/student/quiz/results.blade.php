@extends('layouts.student', ['heading' => 'Quiz Results', 'subheading' => 'Review your answers, grading feedback, and next steps.'])

@section('content')
<div class="stack-lg">
    <section class="card">
        <div class="row-between">
            <div>
                <h3 class="h2">{{ $quiz->subject?->name ?? 'General quiz' }}</h3>
                <p class="muted text-sm mb-0">{{ strtoupper($quiz->mode) }} · {{ $quiz->total_questions }} question(s)</p>
            </div>
            <span id="quiz-status-pill" class="pill {{ $quiz->status === \App\Models\Quiz::STATUS_GRADED ? 'pill-success' : 'pill-muted' }}">{{ strtoupper($quiz->status) }}</span>
        </div>

        <div class="metric-grid" style="margin-top:1rem">
            <div class="summary-tile">
                <p class="muted mb-0 text-sm">Score</p>
                <strong id="quiz-score-text">{{ $quiz->total_awarded_score !== null ? number_format((float) $quiz->total_awarded_score, 2) : 'Pending' }} / {{ number_format((float) $quiz->total_possible_score, 2) }}</strong>
            </div>
            <div class="summary-tile">
                <p class="muted mb-0 text-sm">Submitted</p>
                <strong>{{ optional($quiz->submitted_at)->format('M d, Y H:i') ?? 'N/A' }}</strong>
            </div>
            <div class="summary-tile">
                <p class="muted mb-0 text-sm">Graded</p>
                <strong id="quiz-graded-at-text">{{ optional($quiz->graded_at)->format('M d, Y H:i') ?? 'In progress' }}</strong>
            </div>
        </div>

        @if($quiz->status === \App\Models\Quiz::STATUS_GRADING)
            <p id="quiz-live-status-note" class="muted" style="margin-top:.9rem;margin-bottom:0;">
                Theory grading is still running. You can refresh this page for updated scores and detailed feedback.
            </p>
        @else
            <p id="quiz-live-status-note" class="muted" style="margin-top:.9rem;margin-bottom:0;display:none;"></p>
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
            <article class="card stack-sm quiz-panel" data-answer-id="{{ $answer?->id }}">
                <div class="row-between">
                    <strong>Question {{ $quizQuestion->order_no }} · {{ strtoupper($snapshot['type'] ?? '-') }}</strong>
                    <span class="pill js-answer-status-pill {{ in_array($status, [\App\Models\StudentAnswer::STATUS_GRADED, \App\Models\StudentAnswer::STATUS_OVERRIDDEN], true) ? 'pill-success' : 'pill-muted' }}">{{ strtoupper($status) }}</span>
                </div>

                <p class="mb-0">{{ $snapshot['question_text'] ?? '' }}</p>

                @if(($snapshot['type'] ?? null) === \App\Models\Question::TYPE_MCQ)
                    <div class="stack-sm">
                        @foreach(($snapshot['options'] ?? []) as $option)
                            @php
                                $isSelected = (int) ($answer?->selected_option_id ?? 0) === (int) ($option['id'] ?? 0);
                                $isCorrect = (bool) ($option['is_correct'] ?? false);
                            @endphp
                            <div class="result-option {{ $isSelected ? 'selected' : '' }} {{ $isCorrect ? 'correct' : '' }}">
                                <span><strong>{{ $option['option_key'] ?? '?' }}.</strong> {{ $option['option_text'] ?? '' }}</span>
                                <span class="muted text-sm">
                                    {{ $isSelected ? 'Your choice' : '' }}
                                    {{ $isSelected && $isCorrect ? ' · Correct' : '' }}
                                    {{ $isSelected && ! $isCorrect ? ' · Incorrect' : '' }}
                                    {{ ! $isSelected && $isCorrect ? 'Correct answer' : '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <p class="muted mb-0"><strong>Explanation:</strong> {{ $snapshot['explanation'] ?: 'No explanation provided.' }}</p>
                @else
                    <p class="muted mb-0" style="white-space:pre-wrap;"><strong>Your answer:</strong> {{ $answer?->answer_text ?: 'No answer submitted.' }}</p>

                    @if(in_array($status, [\App\Models\StudentAnswer::STATUS_PENDING, \App\Models\StudentAnswer::STATUS_PROCESSING], true))
                        <p class="muted js-answer-pending-message" style="margin:0">Theory grading pending. Detailed feedback will appear once grading completes.</p>
                        <p class="muted js-answer-feedback" style="white-space:pre-wrap;margin:0;display:none"><strong>Feedback:</strong> <span></span></p>
                        <p class="muted js-answer-ai-meta" style="margin:0;display:none"></p>
                    @else
                        <p class="muted js-answer-pending-message" style="margin:0;display:none"></p>
                        <p class="muted js-answer-feedback" style="white-space:pre-wrap;margin:0"><strong>Feedback:</strong> <span>{{ $answer?->feedback ?: 'No feedback yet.' }}</span></p>
                        @if(!empty($aiParsed))
                            <p class="muted js-answer-ai-meta" style="margin:0">
                                Verdict: {{ strtoupper((string) ($aiParsed['verdict'] ?? 'n/a')) }}
                                · Confidence: {{ $aiParsed['confidence'] ?? 'N/A' }}
                            </p>
                        @else
                            <p class="muted js-answer-ai-meta" style="margin:0;display:none"></p>
                        @endif
                    @endif
                @endif

                <p class="muted js-answer-score" style="margin:0"><strong>Score:</strong> <span>{{ $answer?->score !== null ? number_format((float) $answer->score, 2) : 'Pending' }}</span> / {{ number_format((float) $quizQuestion->max_score, 2) }}</p>
            </article>
        @endforeach
    </section>
</div>
@endsection

@push('scripts')
    <script>
        (() => {
            const quizStatusPill = document.getElementById('quiz-status-pill');
            const quizScoreText = document.getElementById('quiz-score-text');
            const quizGradedAtText = document.getElementById('quiz-graded-at-text');
            const quizStatusNote = document.getElementById('quiz-live-status-note');

            const formatScore = (value) => value === null || typeof value === 'undefined'
                ? 'Pending'
                : Number(value).toFixed(2);

            const formatDateTime = (isoValue) => {
                if (!isoValue) {
                    return 'In progress';
                }

                return new Date(isoValue).toLocaleString();
            };

            const updateQuizSummary = (quiz) => {
                if (!quiz) {
                    return;
                }

                if (quizStatusPill) {
                    quizStatusPill.textContent = String(quiz.status ?? '').toUpperCase();
                    quizStatusPill.classList.remove('pill-success', 'pill-muted');
                    quizStatusPill.classList.add(quiz.status === 'graded' ? 'pill-success' : 'pill-muted');
                }

                if (quizScoreText) {
                    const maxScoreText = Number(quiz.total_possible_score ?? 0).toFixed(2);
                    quizScoreText.textContent = `${formatScore(quiz.total_awarded_score)} / ${maxScoreText}`;
                }

                if (quizGradedAtText) {
                    quizGradedAtText.textContent = formatDateTime(quiz.graded_at);
                }

                if (quizStatusNote) {
                    if (quiz.status === 'grading') {
                        quizStatusNote.style.display = 'block';
                        quizStatusNote.textContent = `Theory grading in progress (${quiz.theory_completed ?? 0}/${quiz.theory_total ?? 0} completed).`;
                    } else if (quiz.status === 'graded') {
                        quizStatusNote.style.display = 'block';
                        quizStatusNote.textContent = 'Theory grading finished. Results are now final unless manually reviewed.';
                    } else {
                        quizStatusNote.style.display = 'none';
                    }
                }
            };

            const updateTheoryAnswerCard = (answer) => {
                if (!answer || !answer.id) {
                    return;
                }

                const card = document.querySelector(`[data-answer-id="${answer.id}"]`);
                if (!card) {
                    return;
                }

                const statusPill = card.querySelector('.js-answer-status-pill');
                const pendingMessage = card.querySelector('.js-answer-pending-message');
                const feedbackRow = card.querySelector('.js-answer-feedback');
                const feedbackText = feedbackRow?.querySelector('span');
                const aiMetaRow = card.querySelector('.js-answer-ai-meta');
                const scoreRow = card.querySelector('.js-answer-score span');

                if (statusPill) {
                    statusPill.textContent = String(answer.grading_status ?? '').toUpperCase();
                    statusPill.classList.remove('pill-success', 'pill-muted');
                    statusPill.classList.add(['graded', 'overridden'].includes(answer.grading_status) ? 'pill-success' : 'pill-muted');
                }

                const isPending = ['pending', 'processing'].includes(answer.grading_status);

                if (pendingMessage) {
                    pendingMessage.style.display = isPending ? 'block' : 'none';
                }

                if (feedbackRow) {
                    feedbackRow.style.display = isPending ? 'none' : 'block';
                }

                if (feedbackText && !isPending) {
                    feedbackText.textContent = answer.feedback || 'No feedback yet.';
                }

                const parsed = answer.ai_result_json?.parsed || {};
                if (aiMetaRow) {
                    if (!isPending && parsed.verdict) {
                        aiMetaRow.style.display = 'block';
                        aiMetaRow.textContent = `Verdict: ${String(parsed.verdict).toUpperCase()} · Confidence: ${parsed.confidence ?? 'N/A'}`;
                    } else {
                        aiMetaRow.style.display = 'none';
                    }
                }

                if (scoreRow) {
                    scoreRow.textContent = formatScore(answer.score);
                }
            };

            const teardown = window.createRealtimeChannel?.('quiz.{{ $quiz->id }}', {
                'quiz.grading.progress.updated': ({ quiz }) => updateQuizSummary(quiz),
                'theory.answer.graded': ({ answer }) => updateTheoryAnswerCard(answer),
            });

            window.addEventListener('beforeunload', () => {
                if (typeof teardown === 'function') {
                    teardown();
                }
            });
        })();
    </script>
@endpush
