@extends('layouts.student', ['heading' => 'Choose Your Learning Level'])

@section('content')
@php
    $selectedLevelValues = collect($selectedLevels ?? [])->map(fn ($value) => (string) $value)->all();
@endphp

<div class="stack-lg level-home" id="levels-home" data-multi-initial="{{ ($multiLevelMode ?? false) ? '1' : '0' }}">
    <section class="card stack-md level-selector-shell section-surface-secondary">
        <div class="row-between">
            <div class="stack-sm">
                <h2 class="section-heading step-heading"><span class="step-index">1</span><span>Select your level</span></h2>
            </div>
            <label class="toggle-row" style="gap:.55rem">
                <span class="text-sm text-strong">Multi-level mode</span>
                <span class="switch">
                    <input type="checkbox" id="multi-level-mode" @checked(($multiLevelMode ?? false))>
                    <span class="switch-track"></span>
                </span>
            </label>
        </div>

        <div class="level-card-grid">
            @foreach($levels as $level)
                @php
                    $isSelected = in_array((string) $level['value'], $selectedLevelValues, true);
                    $icon = $level['value'] === \App\Models\Subject::LEVEL_A ? '🚀' : '🧠';
                @endphp
                <button
                    type="button"
                    class="level-play-card {{ $isSelected ? 'active' : '' }}"
                    data-level-option
                    data-level-value="{{ $level['value'] }}"
                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                >
                    <span class="level-icon">{{ $icon }}</span>
                    <div class="stack-sm">
                        <div class="row-between">
                            <h3 class="h2">{{ $level['label'] }}</h3>
                            <span class="level-badge">{{ $isSelected ? 'Selected' : 'Select' }}</span>
                        </div>
                        <p class="muted mb-0">{{ $level['subjects_count'] }} subjects · {{ $level['questions_count'] }} questions</p>
                    </div>
                </button>
            @endforeach
        </div>

        <div class="actions-row" id="level-actions" style="justify-content:space-between;align-items:center;">
            <p class="muted text-sm mb-0" id="selected-level-hint">Select at least one level to continue.</p>
            <a class="btn btn-primary" id="continue-to-subjects" href="{{ route('student.quiz.setup') }}">Continue to subjects</a>
        </div>
    </section>
</div>

<script>
(() => {
    const root = document.getElementById('levels-home');
    if (!root) return;

    const cards = Array.from(root.querySelectorAll('[data-level-option]'));
    const multiToggle = root.querySelector('#multi-level-mode');
    const continueLink = root.querySelector('#continue-to-subjects');
    const actions = root.querySelector('#level-actions');
    const hint = root.querySelector('#selected-level-hint');

    const isMulti = () => !!multiToggle?.checked;

    const selected = () => cards.filter((card) => card.classList.contains('active')).map((card) => card.dataset.levelValue);

    const nextUrl = (levels) => {
        const params = new URLSearchParams();
        levels.forEach((level) => params.append('levels[]', level));
        return `${@json(route('student.quiz.setup'))}${levels.length ? `?${params.toString()}` : ''}`;
    };

    const syncContinueLink = () => {
        const levels = selected();
        continueLink.href = nextUrl(levels);
        continueLink.classList.toggle('btn-disabled', levels.length === 0);
        actions?.classList.toggle('hide-continue', !isMulti());
        hint.textContent = levels.length > 0
            ? `${levels.length} level${levels.length > 1 ? 's' : ''} selected.`
            : isMulti()
                ? 'Select at least one level to continue.'
                : 'Select a level to continue.';
    };

    const setCardState = (card, state) => {
        card.classList.toggle('active', state);
        card.setAttribute('aria-pressed', state ? 'true' : 'false');
        const badge = card.querySelector('.level-badge');
        if (badge) badge.textContent = state ? 'Selected' : 'Select';
    };

    cards.forEach((card) => {
        card.addEventListener('click', () => {
            const currentlyActive = card.classList.contains('active');

            if (isMulti()) {
                setCardState(card, !currentlyActive);
                syncContinueLink();
            } else {
                cards.forEach((other) => setCardState(other, other === card ? true : false));
                const levels = selected();
                if (levels.length > 0) {
                    window.location.assign(nextUrl(levels));
                    return;
                }
            }
        });
    });

    multiToggle?.addEventListener('change', () => {
        if (!isMulti()) {
            const firstSelected = cards.find((card) => card.classList.contains('active')) || cards[0];
            cards.forEach((card) => setCardState(card, card === firstSelected));
        }
        syncContinueLink();
    });

    syncContinueLink();
})();
</script>
@endsection
