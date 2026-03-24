@props([
    'status' => 'partial',
])

@php
    $map = [
        'correct' => ['icon' => '✅', 'label' => 'Correct', 'class' => 'result-status-correct'],
        'incorrect' => ['icon' => '❌', 'label' => 'Not quite', 'class' => 'result-status-incorrect'],
        'partial' => ['icon' => '⚠️', 'label' => 'Partially graded', 'class' => 'result-status-partial'],
    ];

    $active = $map[$status] ?? $map['partial'];
@endphp

<span class="result-status-badge {{ $active['class'] }} js-answer-status-badge">{{ $active['label'] }}</span>
<span class="result-status-icon js-answer-status-icon" aria-hidden="true">{{ $active['icon'] }}</span>
