@extends('layouts.student', ['heading' => 'Build Your Quiz', 'subheading' => 'Choose subjects, optional topics, and settings for your next practice session.'])

@section('content')
@php
    $selectedLevelValues = collect(old('levels', $selectedLevels ?? []))->map(fn ($value) => (string) $value)->all();
    $selectedSubjectValue = (string) old('subject_id', $selectedSubjectId ?: '');
    $selectedSubjectValues = collect(old('subject_ids', $selectedSubjectIds ?? []))->map(fn ($id) => (string) $id)->all();
    $selectedTopicIds = collect(old('topic_ids', []))->map(fn ($id) => (string) $id)->all();
    $selectedDifficulty = old('difficulty', '');
    $isMultiMode = (bool) old('multi_subject_mode', $multiSubjectMode ?? false);
@endphp

<div class="stack-lg" id="guided-quiz-setup" data-multi-mode-initial="{{ $isMultiMode ? '1' : '0' }}">
    <section class="page-hero">
        <h2 class="h1">Subjects → Topics → Settings</h2>
        <p class="mb-0" style="opacity:.92">Choose what you want to practice, then start your quiz.</p>
        <div class="row-wrap" style="margin-top:.65rem;">
            @foreach($levels as $level)
                @if(in_array((string) $level['value'], $selectedLevelValues, true))
                    <span class="pill">{{ $level['label'] }}</span>
                @endif
            @endforeach
            <a class="btn btn-ghost" href="{{ route('student.levels.index') }}">Change levels</a>
        </div>
    </section>

    @if($subjects->isEmpty())
        <section class="empty-state card">
            <h4>No subjects available for your selected level(s)</h4>
            <p class="muted">Try another level selection or ask an admin to activate content.</p>
            <a class="btn" href="{{ route('student.levels.index') }}">Back to levels</a>
        </section>
    @else
        <form class="card stack-lg quiz-panel guided-form" method="POST" action="{{ route('student.quiz.store') }}" id="guided-quiz-form">
            @csrf

            @foreach($selectedLevelValues as $levelValue)
                <input type="hidden" name="levels[]" value="{{ $levelValue }}">
            @endforeach

            <section class="stack-sm section-block">
                <div class="row-between">
                    <div>
                        <h3 class="h2">1) Select subject(s)</h3>
                        <p class="muted text-sm mb-0">Choose one subject, or enable multi-subject mode to combine them.</p>
                    </div>
                    <label class="toggle-row" style="gap:.55rem">
                        <span class="text-sm text-strong">Multi-subject mode</span>
                        <span class="switch">
                            <input type="checkbox" id="multi-subject-mode" name="multi_subject_mode" value="1" @checked($isMultiMode)>
                            <span class="switch-track"></span>
                        </span>
                    </label>
                </div>

                <div class="card-grid" id="subject-cards">
                    @foreach($subjects as $subject)
                        @php
                            $isChecked = $isMultiMode
                                ? in_array((string) $subject->id, $selectedSubjectValues, true)
                                : $selectedSubjectValue === (string) $subject->id;
                        @endphp
                        <label class="select-card subject-option {{ $isChecked ? 'active' : '' }}" style="--subject-accent: {{ $subject->color ?: '#4f46e5' }};">
                            <input
                                type="checkbox"
                                class="subject-picker"
                                data-subject-id="{{ $subject->id }}"
                                data-subject-name="{{ $subject->name }}"
                                value="{{ $subject->id }}"
                                @checked($isChecked)
                            >
                            <input type="radio" name="subject_id" value="{{ $subject->id }}" class="subject-single-input" @checked(! $isMultiMode && $isChecked)>
                            <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" class="subject-multi-input" @checked($isMultiMode && $isChecked)>
                            <div class="row-between">
                                <span class="select-title">{{ $subject->name }}</span>
                                <span class="pill">{{ \App\Models\Subject::levelLabel($subject->level) }}</span>
                            </div>
                            <span class="muted text-sm">{{ $subject->available_questions_count }} question(s)</span>
                        </label>
                    @endforeach
                </div>
                @error('subject_id') <small class="field-error">{{ $message }}</small> @enderror
                @error('subject_ids') <small class="field-error">{{ $message }}</small> @enderror
            </section>

            <hr class="section-divider">

            <section class="stack-sm section-block">
                <h3 class="h2">2) Select topics <span class="muted text-sm">(Optional)</span></h3>
                <p class="muted text-sm mb-0">Topics are grouped by subject with quick search.</p>

                <div class="stack-md" id="topic-groups">
                    @foreach($subjects as $subject)
                        <article class="card card-soft topic-group" data-subject-id="{{ $subject->id }}" style="display:none;">
                            <div class="row-between">
                                <h4 class="h3">{{ $subject->name }}</h4>
                                <span class="pill">{{ $subject->topics->count() }} topic(s)</span>
                            </div>

                            @if($subject->topics->isEmpty())
                                <p class="muted text-sm mb-0">No active topics yet for this subject.</p>
                            @else
                                <label class="field mb-0">
                                    <span>Search topics</span>
                                    <input type="search" class="topic-search-input" placeholder="Type to filter topics..." data-topic-search>
                                </label>
                                <div class="topic-chip-grid topic-scroll-list" data-topic-list>
                                    @foreach($subject->topics as $topic)
                                        @php $checked = in_array((string) $topic->id, $selectedTopicIds, true); @endphp
                                        <label class="topic-chip {{ $checked ? 'active' : '' }}" data-topic-id="{{ $topic->id }}" data-topic-name="{{ strtolower($topic->name) }}">
                                            <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" @checked($checked)>
                                            <span>{{ $topic->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <small class="muted text-xs" data-topic-empty-message style="display:none;">No topics match your search.</small>
                            @endif
                        </article>
                    @endforeach
                </div>

                @error('topic_ids') <small class="field-error">{{ $message }}</small> @enderror
            </section>

            <hr class="section-divider">

            <section class="stack-sm section-block">
                <h3 class="h2">3) Quiz settings</h3>
                <div class="grid-3">
                    <label class="field">
                        <span>Mode</span>
                        <select name="mode" required>
                            @foreach($modes as $value => $label)
                                <option value="{{ $value }}" @selected(old('mode', \App\Models\Quiz::MODE_MIXED) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('mode') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="field">
                        <span>Question count</span>
                        <input type="number" min="1" max="100" name="question_count" value="{{ old('question_count', $defaultQuestionCount) }}" required>
                        <small class="muted text-xs">Default is 50.</small>
                        @error('question_count') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="field">
                        <span>Difficulty</span>
                        <select name="difficulty">
                            <option value="">Mixed / All</option>
                            @foreach($difficulties as $difficulty)
                                <option value="{{ $difficulty }}" @selected($selectedDifficulty === $difficulty)>{{ ucfirst($difficulty) }}</option>
                            @endforeach
                        </select>
                        @error('difficulty') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                </div>
            </section>

            <div class="actions-row">
                <button type="submit" class="btn btn-primary">Start Quiz</button>
            </div>
        </form>
    @endif
</div>

<script>
(() => {
    const root = document.getElementById('guided-quiz-setup');
    if (!root) return;

    const multiModeInput = root.querySelector('#multi-subject-mode');
    const subjectCards = Array.from(root.querySelectorAll('.subject-option'));
    const topicGroups = Array.from(root.querySelectorAll('.topic-group'));

    const isMulti = () => !!multiModeInput?.checked;

    const selectedSubjectIds = () => subjectCards
        .filter((card) => card.querySelector('.subject-picker')?.checked)
        .map((card) => Number(card.querySelector('.subject-picker')?.dataset.subjectId));

    const syncSubjectInputNames = () => {
        subjectCards.forEach((card) => {
            const picker = card.querySelector('.subject-picker');
            const singleInput = card.querySelector('.subject-single-input');
            const multiInput = card.querySelector('.subject-multi-input');
            if (!picker || !singleInput || !multiInput) return;

            if (isMulti()) {
                picker.type = 'checkbox';
                picker.name = '';
                singleInput.disabled = true;
                singleInput.required = false;
                multiInput.disabled = !picker.checked;
                multiInput.checked = picker.checked;
            } else {
                picker.type = 'radio';
                picker.name = 'subject_picker_single';
                multiInput.disabled = true;
                multiInput.checked = false;
                singleInput.disabled = !picker.checked;
                singleInput.checked = picker.checked;
                singleInput.required = true;
            }
        });
    };

    const syncTopicGroups = () => {
        const selectedIds = selectedSubjectIds();

        topicGroups.forEach((group) => {
            const subjectId = Number(group.dataset.subjectId);
            const visible = selectedIds.includes(subjectId);
            group.style.display = visible ? 'grid' : 'none';

            if (!visible) {
                group.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = false;
                    input.closest('.topic-chip')?.classList.remove('active');
                });
            }
        });
    };

    const refreshSubjects = () => {
        subjectCards.forEach((card) => {
            const input = card.querySelector('.subject-picker');
            card.classList.toggle('active', !!input?.checked);
        });
        syncSubjectInputNames();
        syncTopicGroups();
    };

    subjectCards.forEach((card) => {
        const picker = card.querySelector('.subject-picker');
        if (!picker) return;

        picker.addEventListener('change', () => {
            if (!isMulti()) {
                subjectCards.forEach((otherCard) => {
                    if (otherCard === card) return;
                    const otherPicker = otherCard.querySelector('.subject-picker');
                    if (otherPicker) otherPicker.checked = false;
                });
            }

            refreshSubjects();
        });
    });

    multiModeInput?.addEventListener('change', () => {
        if (!isMulti()) {
            const firstChecked = subjectCards.find((card) => card.querySelector('.subject-picker')?.checked);
            subjectCards.forEach((card) => {
                const picker = card.querySelector('.subject-picker');
                if (!picker) return;
                picker.checked = firstChecked ? card === firstChecked : card === subjectCards[0];
            });
        }

        refreshSubjects();
    });

    root.querySelectorAll('.topic-chip input[type="checkbox"]').forEach((input) => {
        input.addEventListener('change', () => {
            input.closest('.topic-chip')?.classList.toggle('active', input.checked);
        });
    });

    root.querySelectorAll('[data-topic-search]').forEach((searchInput) => {
        searchInput.addEventListener('input', () => {
            const group = searchInput.closest('.topic-group');
            if (!group) return;

            const query = searchInput.value.trim().toLowerCase();
            const chips = Array.from(group.querySelectorAll('.topic-chip'));
            const emptyMessage = group.querySelector('[data-topic-empty-message]');

            let visibleCount = 0;
            chips.forEach((chip) => {
                const name = chip.dataset.topicName || '';
                const match = query === '' || name.includes(query);
                chip.style.display = match ? 'inline-flex' : 'none';
                if (match) visibleCount += 1;
            });

            if (emptyMessage) emptyMessage.style.display = visibleCount === 0 ? 'block' : 'none';
        });
    });

    refreshSubjects();
})();
</script>
@endsection
