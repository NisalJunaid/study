@props([
    'steps' => [],
    'current' => 1,
    'label' => 'Progress',
])

@php
    $total = count($steps);
    $safeTotal = max($total, 1);
    $safeCurrent = max(1, min((int) $current, $safeTotal));
    $progress = (int) round(($safeCurrent / $safeTotal) * 100);
@endphp

<section class="guided-stepper card" aria-label="{{ $label }}" data-guided-stepper>
    <div class="row-between guided-stepper-header">
        <p class="muted text-sm mb-0">{{ $label }}</p>
        <p class="text-sm text-strong mb-0">Step {{ $safeCurrent }} of {{ $safeTotal }}</p>
    </div>
    <div class="guided-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
        <div class="guided-progress-value" style="width: {{ $progress }}%"></div>
    </div>
    <ol class="guided-step-list" data-guided-step-list>
        @foreach($steps as $index => $step)
            @php
                $position = $index + 1;
                $state = $position < $safeCurrent ? 'complete' : ($position === $safeCurrent ? 'current' : 'upcoming');
            @endphp
            <li class="guided-step guided-step-{{ $state }}" data-step-index="{{ $position }}">
                <span class="guided-step-dot" aria-hidden="true">{{ $position < $safeCurrent ? '✓' : $position }}</span>
                <span>{{ $step }}</span>
            </li>
        @endforeach
    </ol>
</section>
