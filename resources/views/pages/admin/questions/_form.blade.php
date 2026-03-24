@php
    $question = $question ?? null;
    $isEdit = (bool) $question;

    $selectedType = old('type', $question?->type ?? \App\Models\Question::TYPE_MCQ);
    $selectedSubjectId = (string) old('subject_id', $question?->subject_id);
    $selectedTopicId = (string) old('topic_id', $question?->topic_id);

    $existingImage = $question?->question_image_path;
    $mcqOptions = $question?->mcqOptions?->map(fn ($option) => [
        'option_key' => $option->option_key,
        'option_text' => $option->option_text,
    ])->all() ?? [];
    $correctOptionKey = $question?->mcqOptions?->firstWhere('is_correct', true)?->option_key;
@endphp

<form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.questions.update', $question) : route('admin.questions.store') }}" class="stack-lg card">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid-2">
        <label class="field">
            <span>Subject</span>
            <select name="subject_id" required data-subject-select>
                <option value="">Select subject...</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected($selectedSubjectId === (string) $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
            @error('subject_id')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Topic (optional)</span>
            <select name="topic_id" data-topic-select>
                <option value="">No topic</option>
                @foreach($topics as $topic)
                    <option value="{{ $topic->id }}" data-subject-id="{{ $topic->subject_id }}" @selected($selectedTopicId === (string) $topic->id)>{{ $topic->name }}</option>
                @endforeach
            </select>
            @error('topic_id')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    <div class="grid-3">
        <label class="field">
            <span>Question Type</span>
            <select name="type" required data-question-type>
                <option value="mcq" @selected($selectedType === 'mcq')>MCQ</option>
                <option value="theory" @selected($selectedType === 'theory')>Theory</option>
            </select>
            @error('type')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Difficulty</span>
            <select name="difficulty">
                <option value="">Unspecified</option>
                @foreach($difficulties as $difficulty)
                    <option value="{{ $difficulty }}" @selected(old('difficulty', $question?->difficulty) === $difficulty)>{{ ucfirst($difficulty) }}</option>
                @endforeach
            </select>
            @error('difficulty')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Marks</span>
            <input type="number" min="0" step="0.25" name="marks" value="{{ old('marks', $question?->marks ?? 1) }}" required>
            @error('marks')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    <label class="field">
        <span>Question Text</span>
        <textarea name="question_text" rows="4" required>{{ old('question_text', $question?->question_text) }}</textarea>
        @error('question_text')<small class="field-error">{{ $message }}</small>@enderror
    </label>

    <div class="grid-2">
        <label class="field">
            <span>Question Image (optional)</span>
            <input type="file" name="question_image" accept="image/*">
            @error('question_image')<small class="field-error">{{ $message }}</small>@enderror

            @if($existingImage)
                <small class="muted">Current: {{ $existingImage }}</small>
                <img src="{{ asset('storage/' . $existingImage) }}" alt="Question image" class="question-image-preview">
            @endif
        </label>

        <label class="field">
            <span>Explanation (optional)</span>
            <textarea name="explanation" rows="4">{{ old('explanation', $question?->explanation) }}</textarea>
            @error('explanation')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    @if($existingImage)
        <label class="checkbox-row">
            <input type="hidden" name="remove_image" value="0">
            <input type="checkbox" name="remove_image" value="1" @checked(old('remove_image'))>
            <span>Remove existing image</span>
        </label>
    @endif

    <label class="checkbox-row">
        <input type="hidden" name="is_published" value="0">
        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $question?->is_published ?? false))>
        <span>Published (available for quiz generation)</span>
    </label>
    @error('is_published')<small class="field-error">{{ $message }}</small>@enderror

    @include('pages.admin.questions._mcq-options', [
        'options' => $mcqOptions,
        'correctOptionKey' => $correctOptionKey,
    ])

    @include('pages.admin.questions._theory-rubric')

    <div class="actions-row">
        <a href="{{ route('admin.questions.index') }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Question' : 'Create Question' }}</button>
    </div>
</form>

<template id="mcq-option-template">
    <div class="card card-soft" data-mcq-option-row>
        <div class="grid-3">
            <label class="field">
                <span>Option Key</span>
                <input type="text" data-name="option_key" maxlength="5" placeholder="A" data-option-key required>
            </label>
            <label class="field" style="grid-column: span 2;">
                <span>Option Text</span>
                <textarea data-name="option_text" rows="2" data-option-text required></textarea>
            </label>
        </div>
        <div class="row-between">
            <label class="checkbox-row">
                <input type="radio" name="correct_option_key" data-correct-radio>
                <span>Mark as correct answer</span>
            </label>
            <button type="button" class="btn btn-danger" data-remove-mcq-option>Remove</button>
        </div>
    </div>
</template>

<script>
    (() => {
        const typeField = document.querySelector('[data-question-type]');
        const mcqFields = document.getElementById('mcq-fields');
        const theoryFields = document.getElementById('theory-fields');
        const subjectField = document.querySelector('[data-subject-select]');
        const topicField = document.querySelector('[data-topic-select]');

        const syncMode = () => {
            const isMcq = typeField.value === 'mcq';
            mcqFields.style.display = isMcq ? 'grid' : 'none';
            theoryFields.style.display = isMcq ? 'none' : 'grid';
        };

        const syncTopics = () => {
            const selectedSubject = subjectField.value;
            Array.from(topicField.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const belongs = option.dataset.subjectId === selectedSubject;
                option.hidden = !belongs;
                if (!belongs && option.selected) {
                    option.selected = false;
                }
            });
        };

        const optionsList = document.getElementById('mcq-options-list');
        const template = document.getElementById('mcq-option-template');

        const reindexOptions = () => {
            Array.from(optionsList.querySelectorAll('[data-mcq-option-row]')).forEach((row, index) => {
                const keyInput = row.querySelector('[data-option-key]');
                const textInput = row.querySelector('[data-option-text]');
                const radio = row.querySelector('[data-correct-radio]');

                keyInput.name = `options[${index}][option_key]`;
                textInput.name = `options[${index}][option_text]`;

                const updateRadioValue = () => {
                    radio.value = keyInput.value;
                };

                updateRadioValue();
                keyInput.oninput = updateRadioValue;
            });
        };

        const enforceMinimumOptionCount = () => {
            const rows = optionsList.querySelectorAll('[data-mcq-option-row]');
            rows.forEach((row) => {
                const removeButton = row.querySelector('[data-remove-mcq-option]');
                removeButton.disabled = rows.length <= 2;
            });
        };

        document.addEventListener('click', (event) => {
            if (event.target.matches('[data-add-mcq-option]')) {
                const fragment = template.content.cloneNode(true);
                optionsList.appendChild(fragment);
                reindexOptions();
                enforceMinimumOptionCount();
            }

            if (event.target.matches('[data-remove-mcq-option]')) {
                event.target.closest('[data-mcq-option-row]')?.remove();
                reindexOptions();
                enforceMinimumOptionCount();
            }
        });

        typeField.addEventListener('change', syncMode);
        subjectField.addEventListener('change', syncTopics);

        syncMode();
        syncTopics();
        reindexOptions();
        enforceMinimumOptionCount();
    })();
</script>
