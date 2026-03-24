@extends('layouts.student', [
    'heading' => 'Quiz In Progress',
    'subheading' => 'Focused one-question flow',
    'minimalHeader' => true,
    'suppressFlash' => true,
])

@section('content')
@php
    $isLockedQuiz = in_array($quiz->status, [\App\Models\Quiz::STATUS_SUBMITTED, \App\Models\Quiz::STATUS_GRADING, \App\Models\Quiz::STATUS_GRADED], true);
    $questionPayload = $quiz->quizQuestions->map(function ($quizQuestion) {
        $snapshot = $quizQuestion->question_snapshot ?? [];
        $answer = $quizQuestion->studentAnswer;
        $idealTime = (int) ($snapshot['ideal_time_seconds'] ?? 90);

        return [
            'id' => $quizQuestion->id,
            'order_no' => $quizQuestion->order_no,
            'type' => $snapshot['type'] ?? null,
            'question_text' => $snapshot['question_text'] ?? '',
            'marks' => $quizQuestion->max_score,
            'ideal_time_seconds' => $idealTime,
            'options' => collect($snapshot['options'] ?? [])->map(fn ($option) => [
                'id' => $option['id'],
                'option_key' => $option['option_key'],
                'option_text' => $option['option_text'],
            ])->values()->all(),
            'answer' => [
                'selected_option_id' => $answer?->selected_option_id,
                'answer_text' => $answer?->answer_text,
                'question_started_at' => optional($answer?->question_started_at)->toIso8601String(),
                'answered_at' => optional($answer?->answered_at)->toIso8601String(),
                'answer_duration_seconds' => $answer?->answer_duration_seconds,
                'ideal_time_seconds' => $answer?->ideal_time_seconds,
            ],
            'locked' => $answer?->answered_at !== null,
        ];
    })->values();
@endphp

<div class="quiz-minimal-wrap" id="quiz-take-app"
    data-questions='@json($questionPayload)'
    data-save-route-template="{{ route('student.quiz.answer.save', ['quiz' => $quiz, 'quizQuestion' => '__QUESTION__']) }}"
    data-csrf="{{ csrf_token() }}"
    data-locked="{{ $isLockedQuiz ? '1' : '0' }}"
>
    <section class="card stack-sm quiz-minimal-header">
        <div class="timer-panel">
            <div class="row-between">
                <strong id="question-counter">Question 1 of {{ $quiz->quizQuestions->count() }}</strong>
                <span class="pill" id="status-pill">In Progress</span>
            </div>
            <div class="timer-track progress-track">
                <div class="timer-fill progress-fill" id="question-timer-fill" style="width:0%"></div>
            </div>
            <div class="row-between text-xs muted">
                <span id="elapsed-time-text">Elapsed: 0s</span>
                <span id="ideal-time-text">Ideal: 0s</span>
            </div>
        </div>
        <div class="quiz-step-list" id="quiz-step-list"></div>
    </section>

    @if($quiz->quizQuestions->isEmpty())
        <section class="empty-state">
            <h4>No quiz questions assigned</h4>
            <p class="muted">This quiz could not be initialized. Please return to quiz setup.</p>
        </section>
    @else
        <section class="card stack-lg quiz-panel quiz-minimal-main" id="active-question-panel"></section>

        @if(! $isLockedQuiz)
            <form method="POST" action="{{ route('student.quiz.submit', $quiz) }}" id="submit-quiz-form" class="actions-row" style="justify-content:flex-end;display:none;">
                @csrf
                <button type="submit" class="btn btn-primary">Submit Quiz</button>
            </form>
        @endif
    @endif
</div>

@if(! $quiz->quizQuestions->isEmpty())
<script>
(() => {
    const root = document.getElementById('quiz-take-app');
    if (!root) return;

    const questions = JSON.parse(root.dataset.questions || '[]');
    const saveTemplate = root.dataset.saveRouteTemplate;
    const csrfToken = root.dataset.csrf;
    const quizLocked = root.dataset.locked === '1';

    const state = { currentIndex: 0, busy: false, timerInterval: null };

    const els = {
        panel: document.getElementById('active-question-panel'),
        counter: document.getElementById('question-counter'),
        steps: document.getElementById('quiz-step-list'),
        submitForm: document.getElementById('submit-quiz-form'),
        statusPill: document.getElementById('status-pill'),
        timerFill: document.getElementById('question-timer-fill'),
        elapsedText: document.getElementById('elapsed-time-text'),
        idealText: document.getElementById('ideal-time-text'),
    };

    const nowIso = () => new Date().toISOString();
    const isAnswered = (question) => !!question.answer?.answered_at;
    const currentQuestion = () => questions[state.currentIndex];
    const sanitize = (value) => String(value || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const selectedOption = (question) => Number(question.answer?.selected_option_id || 0);
    const typedAnswer = (question) => (question.answer?.answer_text || '').trim();

    const ensureStartedAt = (question) => {
        if (!question.answer) question.answer = {};
        if (!question.answer.question_started_at) question.answer.question_started_at = nowIso();
    };

    const renderSteps = () => {
        const html = questions.map((q, i) => {
            let cls = 'quiz-step-dot';
            if (i === state.currentIndex) cls += ' current';
            if (isAnswered(q)) cls += ' done';
            return `<span class="${cls}">${i + 1}</span>`;
        }).join('');

        els.steps.innerHTML = html;
    };

    const updateSummary = () => {
        const done = questions.filter(isAnswered).length;
        els.counter.textContent = `Question ${state.currentIndex + 1} of ${questions.length}`;
        els.statusPill.textContent = quizLocked ? 'Locked' : `${done}/${questions.length} completed`;
        renderSteps();
    };

    const updateTimerUi = () => {
        const question = currentQuestion();
        if (!question) return;

        const ideal = Number(question.ideal_time_seconds || 90);
        let elapsed = 0;

        if (question.answer?.answered_at && question.answer?.answer_duration_seconds != null) {
            elapsed = Number(question.answer.answer_duration_seconds);
        } else {
            const start = question.answer?.question_started_at ? new Date(question.answer.question_started_at).getTime() : Date.now();
            elapsed = Math.max(0, Math.floor((Date.now() - start) / 1000));
        }

        const pct = Math.min(100, Math.round((elapsed / ideal) * 100));
        els.timerFill.style.width = `${pct}%`;
        els.timerFill.classList.remove('timer-warning', 'timer-late');

        if (elapsed > ideal) {
            els.timerFill.classList.add('timer-late');
        } else if (elapsed >= ideal * 0.75) {
            els.timerFill.classList.add('timer-warning');
        }

        els.elapsedText.textContent = `Elapsed: ${elapsed}s`;
        els.idealText.textContent = `Ideal: ${ideal}s`;
    };

    const startTimer = () => {
        if (state.timerInterval) clearInterval(state.timerInterval);
        updateTimerUi();
        state.timerInterval = window.setInterval(updateTimerUi, 250);
    };

    const persistAndLock = async (question, payload) => {
        state.busy = true;

        const response = await fetch(saveTemplate.replace('__QUESTION__', String(question.id)), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });

        state.busy = false;

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'Unable to save answer.');
        }

        question.locked = true;
        question.answer = question.answer || {};
        question.answer.answered_at = payload.answered_at;
        question.answer.answer_duration_seconds = payload.answer_duration_seconds;
        question.answer.ideal_time_seconds = payload.ideal_time_seconds;
    };

    const buildQuestionBody = (question) => {
        const locked = quizLocked || question.locked;
        const progressLabel = `${state.currentIndex + 1}/${questions.length}`;
        let html = `
            <div class="row-between">
                <strong>Question ${progressLabel}</strong>
                <span class="pill">${String(question.type || '').toUpperCase()} · ${Number(question.marks).toFixed(2)} marks</span>
            </div>
            <article class="quiz-text-block">${sanitize(question.question_text)}</article>
        `;

        if (question.type === 'mcq') {
            const optionsHtml = (question.options || []).map((option) => {
                const checked = selectedOption(question) === Number(option.id) ? 'checked' : '';
                const disabled = locked ? 'disabled' : '';
                return `
                    <label class="quiz-option ${checked ? 'active' : ''} ${locked ? 'locked' : ''}">
                        <input type="radio" name="mcq_option" value="${option.id}" ${checked} ${disabled}>
                        <span><strong>${sanitize(option.option_key)}.</strong> ${sanitize(option.option_text)}</span>
                    </label>
                `;
            }).join('');
            html += `<div class="stack-sm">${optionsHtml}</div>`;
        } else {
            const disabled = locked ? 'disabled' : '';
            html += `
                <label class="field" style="margin:0">
                    <span>Your answer</span>
                    <textarea id="theory-answer" rows="8" placeholder="Write your response here..." ${disabled}>${sanitize(question.answer?.answer_text || '')}</textarea>
                </label>
            `;
        }

        html += `
            <div class="actions-row" style="justify-content:flex-end">
                <button type="button" class="btn btn-primary" id="next-question" ${locked && state.currentIndex === questions.length - 1 ? 'disabled' : ''}>
                    ${state.currentIndex === questions.length - 1 ? 'Finish Quiz' : 'Next Question'}
                </button>
            </div>
        `;

        return html;
    };

    const renderQuestion = () => {
        const question = currentQuestion();
        if (!question) return;

        ensureStartedAt(question);
        els.panel.innerHTML = buildQuestionBody(question);

        if (question.type === 'mcq' && !question.locked && !quizLocked) {
            els.panel.querySelectorAll('input[name="mcq_option"]').forEach((input) => {
                input.addEventListener('change', (event) => {
                    question.answer = question.answer || {};
                    question.answer.selected_option_id = Number(event.target.value);
                    renderQuestion();
                });
            });
        }

        if (question.type === 'theory' && !question.locked && !quizLocked) {
            const textarea = document.getElementById('theory-answer');
            textarea?.addEventListener('input', (event) => {
                question.answer = question.answer || {};
                question.answer.answer_text = event.target.value;
            });
        }

        const nextButton = document.getElementById('next-question');
        nextButton?.addEventListener('click', async () => {
            if (state.busy || quizLocked) return;

            const activeQuestion = currentQuestion();
            if (!activeQuestion || activeQuestion.locked) {
                if (state.currentIndex < questions.length - 1) {
                    state.currentIndex += 1;
                    renderQuestion();
                    updateSummary();
                    startTimer();
                }
                return;
            }

            const answeredAt = nowIso();
            const startedAt = activeQuestion.answer?.question_started_at || answeredAt;
            const duration = Math.max(0, Math.round((new Date(answeredAt).getTime() - new Date(startedAt).getTime()) / 1000));
            const ideal = Number(activeQuestion.ideal_time_seconds || 90);

            const payload = {
                question_started_at: startedAt,
                answered_at: answeredAt,
                ideal_time_seconds: ideal,
                answer_duration_seconds: duration,
                answered_on_time: duration <= ideal,
            };

            if (activeQuestion.type === 'mcq') {
                const optionId = selectedOption(activeQuestion);
                if (!optionId) {
                    window.alert('Select an option before continuing.');
                    return;
                }
                payload.selected_option_id = optionId;
            } else {
                const answerText = typedAnswer(activeQuestion);
                if (!answerText) {
                    window.alert('Write your answer before continuing.');
                    return;
                }
                payload.answer_text = answerText;
            }

            nextButton.disabled = true;
            nextButton.textContent = 'Saving...';

            try {
                await persistAndLock(activeQuestion, payload);

                if (state.currentIndex < questions.length - 1) {
                    state.currentIndex += 1;
                    renderQuestion();
                    startTimer();
                } else if (els.submitForm) {
                    els.submitForm.style.display = 'flex';
                    els.submitForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } catch (error) {
                window.alert(error.message || 'Unable to save answer.');
                nextButton.disabled = false;
                nextButton.textContent = state.currentIndex === questions.length - 1 ? 'Finish Quiz' : 'Next Question';
            }

            updateSummary();
        });
    };

    els.submitForm?.addEventListener('submit', (event) => {
        const answeredCount = questions.filter(isAnswered).length;
        const confirmed = window.confirm(`Submit quiz now? You finalized ${answeredCount} of ${questions.length} questions.`);
        if (!confirmed) event.preventDefault();
    });

    const firstUnansweredIndex = questions.findIndex((q) => !isAnswered(q));
    state.currentIndex = firstUnansweredIndex === -1 ? Math.max(0, questions.length - 1) : firstUnansweredIndex;

    updateSummary();
    renderQuestion();
    startTimer();
})();
</script>
@endif
@endsection
