@extends('layouts.student', ['heading' => 'Quiz Setup', 'subheading' => 'Level → Subject → Topics (optional) → Start quiz.'])

@section('content')
@php
    $selectedLevelValue = old('level', $selectedLevel);
    $selectedSubjectValue = (string) old('subject_id', $selectedSubjectId ?: '');
    $selectedTopicIds = collect(old('topic_ids', []))->map(fn ($id) => (string) $id)->all();
    $selectedDifficulty = old('difficulty', '');
@endphp

<div class="stack-lg" id="guided-quiz-setup">
    @if($subjects->isEmpty())
        <section class="empty-state">
            <h4>No subjects available for this level</h4>
            <p class="muted">Select another level or ask admin to activate subjects/questions.</p>
            <a class="btn" href="{{ route('student.levels.index') }}">Back to levels</a>
        </section>
    @else
        <form class="card stack-lg quiz-panel" method="POST" action="{{ route('student.quiz.store') }}" id="guided-quiz-form">
            @csrf

            <section class="stack-sm">
                <h3 class="h2">Step 1: Level</h3>
                <div class="card-grid level-grid">
                    @foreach($levels as $level)
                        <label class="select-card level-option {{ $selectedLevelValue === $level['value'] ? 'active' : '' }}">
                            <input type="radio" name="level" value="{{ $level['value'] }}" @checked($selectedLevelValue === $level['value'])>
                            <span class="select-title">{{ $level['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('level') <small class="field-error">{{ $message }}</small> @enderror
            </section>

            <section class="stack-sm">
                <h3 class="h2">Step 2: Subject</h3>
                <div class="card-grid" id="subject-cards">
                    @foreach($subjects as $subject)
                        <label class="select-card subject-option {{ $selectedSubjectValue === (string) $subject->id ? 'active' : '' }}" style="--subject-accent: {{ $subject->color ?: '#4f46e5' }};">
                            <input type="radio" name="subject_id" value="{{ $subject->id }}" @checked($selectedSubjectValue === (string) $subject->id) required>
                            <span class="select-title">{{ $subject->name }}</span>
                            <span class="muted text-sm">{{ $subject->available_questions_count }} question(s)</span>
                        </label>
                    @endforeach
                </div>
                @error('subject_id') <small class="field-error">{{ $message }}</small> @enderror
            </section>

            <section class="stack-sm">
                <div class="row-between">
                    <h3 class="h2">Step 3: Topics <span class="muted text-sm">(Optional)</span></h3>
                </div>
                <p class="muted text-sm mb-0">Choose topic chips to focus practice, or leave blank for full-subject coverage.</p>

                <div class="topic-chip-grid" id="topic-chip-grid">
                    @foreach($subjects as $subject)
                        @foreach($subject->topics as $topic)
                            @php
                                $checked = in_array((string) $topic->id, $selectedTopicIds, true);
                            @endphp
                            <label
                                class="topic-chip {{ $checked ? 'active' : '' }}"
                                data-subject-id="{{ $subject->id }}"
                                data-topic-id="{{ $topic->id }}"
                                style="{{ $selectedSubjectValue !== (string) $subject->id ? 'display:none;' : '' }}"
                            >
                                <input
                                    type="checkbox"
                                    name="topic_ids[]"
                                    value="{{ $topic->id }}"
                                    @checked($checked)
                                >
                                <span>{{ $topic->name }}</span>
                            </label>
                        @endforeach
                    @endforeach
                </div>
                @error('topic_ids') <small class="field-error">{{ $message }}</small> @enderror
            </section>

            <section class="stack-sm">
                <h3 class="h2">Step 4: Quiz Settings</h3>
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
                        <small class="muted text-xs">Default is 50. Adjust for shorter or longer sessions.</small>
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

            <div class="actions-row" style="justify-content:space-between">
                <a href="{{ route('student.levels.index') }}" class="btn">Back to levels</a>
                <button type="submit" class="btn btn-primary">Start Quiz</button>
            </div>
        </form>
    @endif
</div>

<script>
(() => {
    const root = document.getElementById('guided-quiz-setup');
    if (!root) return;

    const levelInputs = Array.from(root.querySelectorAll('input[name="level"]'));
    const subjectInputs = Array.from(root.querySelectorAll('input[name="subject_id"]'));
    const topicChips = Array.from(root.querySelectorAll('.topic-chip'));

    const activateCardState = (inputs, optionClass) => {
        inputs.forEach((input) => {
            const card = input.closest(`.${optionClass}`);
            if (!card) return;
            card.classList.toggle('active', input.checked);
        });
    };

    const updateTopicVisibility = () => {
        const selectedSubjectId = root.querySelector('input[name="subject_id"]:checked')?.value;

        topicChips.forEach((chip) => {
            const topicSubjectId = chip.dataset.subjectId;
            const input = chip.querySelector('input[type="checkbox"]');
            const visible = selectedSubjectId && topicSubjectId === selectedSubjectId;

            chip.style.display = visible ? 'inline-flex' : 'none';

            if (!visible && input) {
                input.checked = false;
                chip.classList.remove('active');
            }
        });
    };

    levelInputs.forEach((input) => {
        input.addEventListener('change', () => {
            activateCardState(levelInputs, 'level-option');
            window.location.href = `{{ route('student.quiz.setup') }}?level=${encodeURIComponent(input.value)}`;
        });
    });

    subjectInputs.forEach((input) => {
        input.addEventListener('change', () => {
            activateCardState(subjectInputs, 'subject-option');
            updateTopicVisibility();
        });
    });

    topicChips.forEach((chip) => {
        const input = chip.querySelector('input[type="checkbox"]');
        input?.addEventListener('change', () => {
            chip.classList.toggle('active', input.checked);
        });
    });

    activateCardState(levelInputs, 'level-option');
    activateCardState(subjectInputs, 'subject-option');
    updateTopicVisibility();
})();
</script>
@endsection
