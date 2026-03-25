@extends('layouts.student', ['heading' => 'Quiz Results', 'subheading' => 'Great effort — here is a clear breakdown of your performance.'])

@section('content')
@php
    $awardedScore = $quiz->total_awarded_score !== null ? (float) $quiz->total_awarded_score : null;
    $possibleScore = (float) $quiz->total_possible_score;
    $scoreRatio = ($awardedScore !== null && $possibleScore > 0)
        ? max(0, min(1, $awardedScore / $possibleScore))
        : 0;
    $accuracyPercent = ($awardedScore !== null && $possibleScore > 0)
        ? round(($awardedScore / $possibleScore) * 100)
        : null;

    $summaryTone = match (true) {
        $accuracyPercent !== null && $accuracyPercent >= 80 => 'high',
        $accuracyPercent !== null && $accuracyPercent >= 50 => 'mid',
        default => 'low',
    };

    $encouragement = match ($summaryTone) {
        'high' => 'Excellent work! You are building strong mastery.',
        'mid' => 'Nice progress! A little more practice will boost your score.',
        default => 'Good effort — keep going, every attempt helps you improve.',
    };

    $totalTimeSeconds = (int) $quiz->quizQuestions
        ->pluck('studentAnswer.answer_duration_seconds')
        ->filter(fn ($seconds) => $seconds !== null)
        ->sum();

    $formatDuration = static function (int $seconds): string {
        if ($seconds <= 0) {
            return 'Not recorded';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $remainingSeconds);
        }

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    };
@endphp

<div class="results-shell stack-lg" id="quiz-results-shell" data-results-poll-url="{{ route('student.quiz.results', $quiz) }}" data-results-poll-enabled="{{ $quiz->status === \App\Models\Quiz::STATUS_GRADING ? '1' : '0' }}">
    <x-student.result-summary-card
        :subject-name="$quiz->subject?->name ?? 'General quiz'"
        :mode-label="strtoupper($quiz->mode)"
        :question-count="$quiz->total_questions"
        :score-text="$awardedScore !== null ? number_format($awardedScore, 2).' / '.number_format($possibleScore, 2) : 'Pending / '.number_format($possibleScore, 2)"
        :accuracy-percent="$accuracyPercent"
        :total-time="$formatDuration($totalTimeSeconds)"
        :message="$encouragement"
        :progress-percent="(int) round($scoreRatio * 100)"
        :tone="$summaryTone"
        :quiz-status="$quiz->status"
    />

    <section class="stack-md">
        <div class="card section-surface-secondary">
            <h2 class="section-heading">Question breakdown</h2>
            <p class="section-intro">Review each response, feedback, and awarded marks.</p>
        </div>

        @foreach($quiz->quizQuestions as $quizQuestion)
            @php
                $snapshot = $quizQuestion->question_snapshot ?? [];
                $answer = $quizQuestion->studentAnswer;
                $status = $answer?->grading_status ?? \App\Models\StudentAnswer::STATUS_PENDING;
                $scoreValue = $answer?->score;
                $maxScore = (float) $quizQuestion->max_score;
                $feedbackText = $answer?->feedback
                    ?? (($snapshot['type'] ?? null) === \App\Models\Question::TYPE_MCQ ? ($snapshot['explanation'] ?: null) : null)
                    ?? 'Keep practicing — review this topic and try again.';

                $yourAnswerText = 'No answer submitted.';

                if (($snapshot['type'] ?? null) === \App\Models\Question::TYPE_MCQ) {
                    $selectedOption = collect($snapshot['options'] ?? [])->first(
                        fn ($option) => (int) ($option['id'] ?? 0) === (int) ($answer?->selected_option_id ?? 0)
                    );

                    $yourAnswerText = $selectedOption
                        ? (($selectedOption['option_key'] ?? '?').'. '.($selectedOption['option_text'] ?? ''))
                        : 'No option selected.';
                } elseif (! empty($answer?->answer_text)) {
                    $yourAnswerText = $answer->answer_text;
                }

                $visualStatus = 'partial';
                if (in_array($status, [\App\Models\StudentAnswer::STATUS_PENDING, \App\Models\StudentAnswer::STATUS_PROCESSING, \App\Models\StudentAnswer::STATUS_MANUAL_REVIEW], true)) {
                    $visualStatus = 'partial';
                } elseif ($scoreValue !== null && $maxScore > 0 && (float) $scoreValue >= $maxScore) {
                    $visualStatus = 'correct';
                } elseif ($scoreValue !== null && (float) $scoreValue <= 0) {
                    $visualStatus = 'incorrect';
                } elseif (($snapshot['type'] ?? null) === \App\Models\Question::TYPE_MCQ) {
                    $visualStatus = $answer?->is_correct ? 'correct' : 'incorrect';
                }
            @endphp

            <x-student.question-result-card
                :question-number="$quizQuestion->order_no"
                :question-text="$snapshot['question_text'] ?? ''"
                :answer-text="$yourAnswerText"
                :feedback-text="$feedbackText"
                :score-text="$scoreValue !== null ? number_format((float) $scoreValue, 2) : 'Pending'"
                :max-score="number_format($maxScore, 2)"
                :status="$visualStatus"
                :answer-id="$answer?->id"
                :is-pending="in_array($status, [\App\Models\StudentAnswer::STATUS_PENDING, \App\Models\StudentAnswer::STATUS_PROCESSING], true)"
            />
        @endforeach
    </section>
</div>
@endsection

@push('scripts')
    <script>
        (() => {
            const quizStatusPill = document.getElementById('quiz-status-pill');
            const quizScoreText = document.getElementById('quiz-score-text');
            const quizAccuracyText = document.getElementById('quiz-accuracy-text');
            const quizProgressBar = document.getElementById('quiz-score-progress');

            const formatScore = (value) => value === null || typeof value === 'undefined'
                ? 'Pending'
                : Number(value).toFixed(2);

            const scorePercent = (score, total) => {
                const safeTotal = Number(total ?? 0);
                const safeScore = Number(score ?? 0);
                if (safeTotal <= 0) {
                    return 0;
                }

                return Math.max(0, Math.min(100, Math.round((safeScore / safeTotal) * 100)));
            };

            const summaryToneClass = (percent) => {
                if (percent >= 80) return 'result-tone-high';
                if (percent >= 50) return 'result-tone-mid';
                return 'result-tone-low';
            };

            const updateQuizSummary = (quiz) => {
                if (!quiz) {
                    return;
                }

                const percent = scorePercent(quiz.total_awarded_score, quiz.total_possible_score);

                if (quizStatusPill) {
                    quizStatusPill.textContent = quiz.status === 'grading'
                        ? 'Still grading'
                        : quiz.status === 'graded'
                            ? 'Final score'
                            : 'In progress';
                }

                if (quizScoreText) {
                    const maxScoreText = Number(quiz.total_possible_score ?? 0).toFixed(2);
                    quizScoreText.textContent = `${formatScore(quiz.total_awarded_score)} / ${maxScoreText}`;
                }

                if (quizAccuracyText) {
                    quizAccuracyText.textContent = `${percent}% accuracy`;
                }

                if (quizProgressBar) {
                    quizProgressBar.style.width = `${percent}%`;
                    const summaryCard = quizProgressBar.closest('.result-summary-card');
                    if (summaryCard) {
                        summaryCard.classList.remove('result-tone-high', 'result-tone-mid', 'result-tone-low');
                        summaryCard.classList.add(summaryToneClass(percent));
                    }
                }
            };

            const cardStatusFromAnswer = (answer) => {
                if (['pending', 'processing', 'manual_review'].includes(answer.grading_status)) {
                    return 'partial';
                }

                if (typeof answer.score !== 'undefined' && answer.score !== null) {
                    if (Number(answer.score) <= 0) return 'incorrect';
                    if (answer.max_score && Number(answer.score) >= Number(answer.max_score)) return 'correct';
                }

                if (answer.is_correct === true) return 'correct';
                if (answer.is_correct === false) return 'incorrect';
                return 'partial';
            };

            const updateTheoryAnswerCard = (answer) => {
                if (!answer || !answer.id) {
                    return;
                }

                const card = document.querySelector(`[data-answer-id="${answer.id}"]`);
                if (!card) {
                    return;
                }

                const statusBadge = card.querySelector('.js-answer-status-badge');
                const pendingMessage = card.querySelector('.js-answer-pending-message');
                const feedbackText = card.querySelector('.js-answer-feedback-text');
                const scoreText = card.querySelector('.js-answer-score-text');
                const icon = card.querySelector('.js-answer-status-icon');

                const visualStatus = cardStatusFromAnswer(answer);
                card.classList.remove('result-card-correct', 'result-card-incorrect', 'result-card-partial');
                card.classList.add(`result-card-${visualStatus}`);

                if (statusBadge) {
                    statusBadge.classList.remove('result-status-correct', 'result-status-incorrect', 'result-status-partial');
                    statusBadge.classList.add(`result-status-${visualStatus}`);
                    statusBadge.textContent = visualStatus === 'correct'
                        ? 'Correct'
                        : visualStatus === 'incorrect'
                            ? 'Not quite'
                            : 'Partially graded';
                }

                if (icon) {
                    icon.textContent = visualStatus === 'correct' ? '✅' : visualStatus === 'incorrect' ? '❌' : '⚠️';
                }

                const isPending = ['pending', 'processing'].includes(answer.grading_status);

                if (pendingMessage) {
                    pendingMessage.style.display = isPending ? 'block' : 'none';
                }

                if (feedbackText && !isPending) {
                    feedbackText.textContent = answer.feedback || 'Keep practicing — review this topic and try again.';
                }

                if (scoreText) {
                    scoreText.textContent = formatScore(answer.score);
                }
            };

            const resultsShell = document.getElementById('quiz-results-shell');
            const pollEnabled = resultsShell?.dataset.resultsPollEnabled === '1';
            const pollUrl = resultsShell?.dataset.resultsPollUrl;
            let pollInterval = null;

            const fetchLatestResults = async () => {
                if (!pollUrl) {
                    return;
                }

                const response = await fetch(`${pollUrl}?format=json`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Unable to poll quiz results.');
                }

                const payload = await response.json();

                if (payload.quiz) {
                    updateQuizSummary(payload.quiz);
                }

                if (Array.isArray(payload.answers)) {
                    payload.answers.forEach((answer) => updateTheoryAnswerCard(answer));
                }

                if (payload.quiz?.status === 'graded' && pollInterval) {
                    window.clearInterval(pollInterval);
                    pollInterval = null;
                }
            };

            const teardown = window.createRealtimeChannel?.('quiz.{{ $quiz->id }}', {
                'quiz.grading.progress.updated': ({ quiz }) => updateQuizSummary(quiz),
                'theory.answer.graded': ({ answer }) => updateTheoryAnswerCard(answer),
            });

            if (pollEnabled) {
                pollInterval = window.setInterval(() => {
                    fetchLatestResults().catch(() => {});
                }, 6000);
            }

            window.addEventListener('beforeunload', () => {
                if (typeof teardown === 'function') {
                    teardown();
                }

                if (pollInterval) {
                    window.clearInterval(pollInterval);
                }
            });
        })();
    </script>
@endpush
