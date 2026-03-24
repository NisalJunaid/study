@props([
    'questionNumber',
    'questionText',
    'answerText',
    'feedbackText',
    'scoreText',
    'maxScore',
    'status' => 'partial',
    'answerId' => null,
    'isPending' => false,
])

<article class="card question-result-card result-card-{{ $status }}" @if($answerId) data-answer-id="{{ $answerId }}" @endif>
    <div class="row-between result-question-top">
        <div class="row-wrap">
            <strong class="result-question-label">Question {{ $questionNumber }}</strong>
            <x-student.score-badge :status="$status" />
        </div>
        <p class="result-question-score mb-0"><span class="js-answer-score-text">{{ $scoreText }}</span>/{{ $maxScore }}</p>
    </div>

    <p class="result-question-text mb-0">{{ $questionText }}</p>

    <section class="result-answer-block stack-sm">
        <p class="result-section-heading mb-0">Your answer</p>
        <p class="result-answer-copy mb-0" style="white-space: pre-wrap;">{{ $answerText }}</p>
    </section>

    <x-student.feedback-block :text="$feedbackText" :pending="$isPending" />
</article>
