@extends('layouts.student', ['heading' => 'Build Quiz'])

@section('content')
@php
    $selectedLevelValues = collect(old('levels', $selectedLevels ?? []))->map(fn ($value) => (string) $value)->all();
    $selectedSubjectValue = (string) old('subject_id', $selectedSubjectId ?: '');
    $selectedSubjectValues = collect(old('subject_ids', $selectedSubjectIds ?? []))->map(fn ($id) => (string) $id)->all();
    $selectedTopicIds = collect(old('topic_ids', $defaultTopicIds ?? []))->map(fn ($id) => (string) $id)->all();
    $selectedDifficulty = old('difficulty', '');
    $isMultiMode = (bool) old('multi_subject_mode', $multiSubjectMode ?? false);
    $initialStep = (int) old('guided_step', 1);
    $initialStep = $initialStep < 1 ? 1 : min(5, $initialStep);
@endphp

<div class="stack-lg" id="guided-quiz-setup" data-multi-mode-initial="{{ $isMultiMode ? '1' : '0' }}" data-initial-step="{{ $initialStep }}">
    @if(($billingAccess['allowed'] ?? false) === false)
        <section class="card stack-sm section-surface-secondary">
            <strong>Quiz access is limited.</strong>
            <p class="mb-0">{{ $billingAccess['message'] ?? 'Open billing to continue.' }}</p>
            @if(($billingAccess['reason'] ?? null) === \App\Services\Billing\QuizAccessService::REASON_DAILY_LIMIT_REACHED)
                <p class="mb-0 muted">You can continue any existing draft quiz, but starting a new quiz is blocked until quota resets.</p>
            @elseif(($billingAccess['reason'] ?? null) === \App\Services\Billing\QuizAccessService::REASON_TEMPORARY_ACCESS_EXPIRED)
                <p class="mb-0 muted">Temporary access is no longer valid. Upload a new payment proof to regain temporary access.</p>
            @elseif(($billingAccess['reason'] ?? null) === \App\Services\Billing\QuizAccessService::REASON_PENDING_VERIFICATION)
                <p class="mb-0 muted">Your payment is pending admin review. Temporary access applies while quota remains.</p>
            @elseif(($billingAccess['reason'] ?? null) === \App\Services\Billing\QuizAccessService::REASON_BILLING_CONFIGURATION_INVALID)
                <p class="mb-0 muted">We cannot process new quiz access right now. Please contact support.</p>
            @endif
            <a class="btn mt-2" href="{{ route('student.billing.subscription', ['start_payment' => 1]) }}">Open billing</a>
        </section>
    @elseif(($billingAccess['access_type'] ?? null) === \App\Services\Billing\QuizAccessService::ACCESS_FREE_TRIAL)
        <section class="card stack-sm section-surface-secondary">
            <strong>Free trial:</strong> You can start one quiz with up to 10 questions.
        </section>
    @elseif(($billingAccess['access_type'] ?? null) === \App\Services\Billing\QuizAccessService::ACCESS_TEMPORARY_PENDING_PAYMENT)
        <section class="card stack-sm section-surface-secondary">
            <strong>Payment received — access active.</strong>
            <p class="mb-0">{{ $billingAccess['message'] }}</p>
        </section>
    @endif

    <x-guided.stepper
        :steps="['Levels', 'Subjects', 'Topics', 'Settings', 'Review']"
        :current="$initialStep"
        label="Setup progress"
    />

    @if($subjects->isEmpty())
        <section class="empty-state card">
            <h4>No subjects available</h4>
            <a class="btn" href="{{ route('student.levels.index') }}">Back</a>
        </section>
    @else
        <form class="card stack-lg quiz-panel guided-form" method="POST" action="{{ route('student.quiz.store') }}" id="guided-quiz-form">
            @csrf
            <input type="hidden" name="guided_step" value="{{ $initialStep }}" data-guided-current-step-input>

            <section class="stack-sm section-block guided-step-pane" data-guided-step="1">
                <h2 class="section-heading">Step 1: Levels</h2>

                <label class="toggle-row" style="gap:.55rem; max-width:fit-content;">
                    <span class="text-sm text-strong">Multi-level</span>
                    <span class="switch">
                        <input type="checkbox" id="multi-level-mode" @checked(count($selectedLevelValues) > 1)>
                        <span class="switch-track"></span>
                    </span>
                </label>

                <div class="card-grid">
                    @foreach($levels as $level)
                        @php $isLevelSelected = in_array((string) $level['value'], $selectedLevelValues, true); @endphp
                        <button
                            type="button"
                            class="select-card level-option {{ $isLevelSelected ? 'active' : '' }}"
                            data-level-option
                            data-level-value="{{ $level['value'] }}"
                        >
                            <span class="select-title">{{ $level['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                @foreach($levels as $level)
                    @php $isLevelSelected = in_array((string) $level['value'], $selectedLevelValues, true); @endphp
                    <input type="checkbox" name="levels[]" value="{{ $level['value'] }}" @checked($isLevelSelected) @disabled(! $isLevelSelected) hidden>
                @endforeach
                @error('levels') <small class="field-error">{{ $message }}</small> @enderror
                <small class="field-error" data-step-error="1" hidden></small>
            </section>

            <section class="stack-sm section-block guided-step-pane" data-guided-step="2" hidden>
                <div class="row-between">
                    <div>
                        <h2 class="section-heading">Step 2: Subjects</h2>
                    </div>
                    <label class="toggle-row" style="gap:.55rem">
                        <span class="text-sm text-strong">Multi-subject</span>
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
                            $subjectColor = \App\Models\Subject::normalizeColor($subject->color);
                        @endphp
                        <label class="select-card subject-option {{ $isChecked ? 'active' : '' }}" data-subject-level="{{ $subject->level }}" style="--subject-accent: {{ $subjectColor }}; --subject-tint: {{ \App\Models\Subject::colorToRgba($subjectColor, 0.16) }};">
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
                                <span class="select-title row-wrap"><span class="subject-color-dot" aria-hidden="true"></span>{{ $subject->name }}</span>
                                <span class="pill">{{ \App\Models\Subject::levelLabel($subject->level) }}</span>
                            </div>
                            <span class="muted text-sm">{{ $subject->available_questions_count }} questions</span>
                        </label>
                    @endforeach
                </div>
                @error('subject_id') <small class="field-error">{{ $message }}</small> @enderror
                @error('subject_ids') <small class="field-error">{{ $message }}</small> @enderror
                <small class="field-error" data-step-error="2" hidden></small>
            </section>

            <section class="stack-sm section-block guided-step-pane" data-guided-step="3" hidden>
                <h2 class="section-heading">Step 3: Topics (optional)</h2>
                <label class="field input-field">
                    <span>Search topics</span>
                    <input type="search" class="field-control input-control" id="shared-topic-search" placeholder="Search" autocomplete="off">
                </label>

                <div class="stack-md" id="topic-groups">
                    @foreach($subjects as $subject)
                        @php $topicSubjectColor = \App\Models\Subject::normalizeColor($subject->color); @endphp
                        <article class="card card-soft subject-card topic-group" data-subject-id="{{ $subject->id }}" style="display:none; --subject-accent: {{ $topicSubjectColor }}; --subject-tint: {{ \App\Models\Subject::colorToRgba($topicSubjectColor, 0.12) }};">
                            <div class="row-between">
                                <h3 class="h3 row-wrap"><span class="subject-color-dot" aria-hidden="true"></span>{{ $subject->name }}</h3>
                                <span class="pill">{{ $subject->topics->count() }} topic(s)</span>
                            </div>

                            @if($subject->topics->isEmpty())
                                <p class="muted text-sm mb-0">No active topics.</p>
                            @else
                                <div class="topic-chip-grid topic-scroll-list" data-topic-list>
                                    @foreach($subject->topics as $topic)
                                        @php $checked = in_array((string) $topic->id, $selectedTopicIds, true); @endphp
                                        <label class="topic-chip {{ $checked ? 'active' : '' }}" data-topic-id="{{ $topic->id }}" data-topic-name="{{ strtolower($topic->name) }}">
                                            <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" @checked($checked)>
                                            <span>{{ $topic->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <small class="muted text-xs" data-topic-empty-message style="display:none;">No matches.</small>
                            @endif
                        </article>
                    @endforeach
                </div>

                @error('topic_ids') <small class="field-error">{{ $message }}</small> @enderror
            </section>

            <section class="stack-sm section-block guided-step-pane" data-guided-step="4" hidden>
                <h2 class="section-heading">Step 4: Settings</h2>
                <div class="grid-3">
                    <label class="field input-field">
                        <span>Mode</span>
                        <select name="mode" class="input-control" required>
                            @foreach($modes as $value => $label)
                                <option value="{{ $value }}" @selected(old('mode', $defaultMode ?? \App\Models\Quiz::MODE_MIXED) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('mode') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="field input-field">
                        <span>Questions</span>
                        <input type="number" class="input-control" min="1" max="100" name="question_count" value="{{ old('question_count', $defaultQuestionCount) }}" required>
                        <small class="muted text-xs">Default: 50. Trial cap: 10.</small>
                        @error('question_count') <small class="field-error">{{ $message }}</small> @enderror
                    </label>

                    <label class="field input-field">
                        <span>Difficulty</span>
                        <select name="difficulty" class="input-control">
                            <option value="">All</option>
                            @foreach($difficulties as $difficulty)
                                <option value="{{ $difficulty }}" @selected($selectedDifficulty === $difficulty)>{{ ucfirst($difficulty) }}</option>
                            @endforeach
                        </select>
                        @error('difficulty') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                </div>
                <small class="field-error" data-step-error="4" hidden></small>
            </section>

            <section class="stack-sm section-block guided-step-pane" data-guided-step="5" hidden>
                <h2 class="section-heading">Step 5: Review</h2>
                <div class="guided-summary" data-quiz-summary></div>
            </section>

            <div class="actions-row row-between">
                <button type="button" class="btn" data-guided-prev hidden disabled>Back</button>
                <div class="row-wrap">
                    <button type="button" class="btn btn-primary" data-guided-next>Continue</button>
                    <button type="submit" class="btn btn-primary" data-guided-submit hidden disabled>Start Quiz</button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
