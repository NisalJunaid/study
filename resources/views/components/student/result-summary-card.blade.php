@props([
    'subjectName',
    'modeLabel',
    'questionCount',
    'scoreText',
    'accuracyPercent' => null,
    'totalTime',
    'message',
    'progressPercent' => 0,
    'tone' => 'low',
    'quizStatus' => 'draft',
])

<section class="card result-summary-card result-tone-{{ $tone }}">
    <div class="row-between">
        <div class="stack-sm">
            <p class="muted mb-0 text-sm">{{ $subjectName }} · {{ $modeLabel }} · {{ $questionCount }} question(s)</p>
            <h2 id="quiz-score-text" class="result-score-display">{{ $scoreText }}</h2>
            <div class="row-wrap result-summary-meta">
                <span id="quiz-accuracy-text" class="pill result-meta-pill">{{ $accuracyPercent !== null ? $accuracyPercent.'% accuracy' : 'Accuracy pending' }}</span>
                <span class="pill result-meta-pill">⏱️ {{ $totalTime }}</span>
                <span id="quiz-status-pill" class="pill result-meta-pill">
                    {{ $quizStatus === \App\Models\Quiz::STATUS_GRADING ? 'Still grading' : ($quizStatus === \App\Models\Quiz::STATUS_GRADED ? 'Final score' : 'In progress') }}
                </span>
            </div>
        </div>
        <p class="result-summary-message mb-0">🌟 {{ $message }}</p>
    </div>

    <div class="progress-track result-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPercent }}">
        <div id="quiz-score-progress" class="progress-fill result-progress-fill" style="width: {{ $progressPercent }}%"></div>
    </div>
</section>
