@extends('layouts.student', ['heading' => 'Quiz In Progress', 'subheading' => 'Focused mode: question, timer, progress, answer.'])

@section('content')
@php
    $isLocked = in_array($quiz->status, [\App\Models\Quiz::STATUS_SUBMITTED, \App\Models\Quiz::STATUS_GRADING, \App\Models\Quiz::STATUS_GRADED], true);
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
                'answered_on_time' => $answer?->answered_on_time,
            ],
        ];
    })->values();
@endphp

<div class="stack-lg" id="quiz-take-app"
    data-questions='@json($questionPayload)'
    data-save-route-template="{{ route('student.quiz.answer.save', ['quiz' => $quiz, 'quizQuestion' => '__QUESTION__']) }}"
    data-csrf="{{ csrf_token() }}"
    data-locked="{{ $isLocked ? '1' : '0' }}"
>
    <section class="card quiz-focus-header">
        <div class="row-between">
            <strong id="question-counter">Question 1 of {{ $quiz->quizQuestions->count() }}</strong>
            <span class="pill">{{ strtoupper($quiz->mode) }}</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="overall-progress-fill" style="width: 0%"></div>
        </div>
        <p class="muted text-sm mb-0">{{ $quiz->subject?->name ?? 'General quiz' }} · {{ $quiz->total_questions }} questions · {{ $quiz->total_possible_score }} marks</p>
    </section>

    @if($quiz->quizQuestions->isEmpty())
        <section class="empty-state">
            <h4>No quiz questions assigned</h4>
            <p class="muted">This quiz could not be initialized. Please return to quiz setup.</p>
        </section>
    @else
        <section class="card stack-md quiz-panel quiz-focus-body">
            <div class="row-between">
                <span class="pill" id="autosave-indicator">All changes saved</span>
                <span class="pill timer-pill" id="timer-pill">Timer: --</span>
            </div>

            <div class="progress-track timer-track">
                <div class="progress-fill timer-fill" id="timer-progress-fill" style="width: 0%"></div>
            </div>

            <article class="card card-soft stack-md" id="active-question-panel"></article>

            <div class="actions-row" style="justify-content:space-between;align-items:center">
                <div class="actions-inline">
                    <button type="button" class="btn" id="prev-question">Previous</button>
                    <button type="button" class="btn" id="next-question">Next</button>
                </div>

                @if(! $isLocked)
                    <form method="POST" action="{{ route('student.quiz.submit', $quiz) }}" id="submit-quiz-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">Submit Quiz</button>
                    </form>
                @endif
            </div>
        </section>
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
    const isLocked = root.dataset.locked === '1';

    const nowIso = () => new Date().toISOString();
    const toTimestamp = (value) => value ? new Date(value).getTime() : null;

    const state = {
        currentIndex: 0,
        saveTimers: new Map(),
        savingByQuestion: new Map(),
        pendingByQuestion: new Map(),
        timerInterval: null,
    };

    const els = {
        panel: document.getElementById('active-question-panel'),
        counter: document.getElementById('question-counter'),
        autosave: document.getElementById('autosave-indicator'),
        prev: document.getElementById('prev-question'),
        next: document.getElementById('next-question'),
        submitForm: document.getElementById('submit-quiz-form'),
        timerPill: document.getElementById('timer-pill'),
        timerFill: document.getElementById('timer-progress-fill'),
        overallFill: document.getElementById('overall-progress-fill'),
    };

    const isAnswered = (question) => {
        if (question.type === 'mcq') return !!question.answer?.selected_option_id;
        return !!(question.answer?.answer_text || '').trim();
    };

    const updateAutosaveLabel = () => {
        const currentQuestion = questions[state.currentIndex];
        if (!currentQuestion) return;

        if (state.savingByQuestion.get(currentQuestion.id)) {
            els.autosave.textContent = 'Saving...';
            return;
        }

        if (state.pendingByQuestion.get(currentQuestion.id)) {
            els.autosave.textContent = 'Unsaved changes';
            return;
        }

        els.autosave.textContent = 'All changes saved';
    };

    const updateOverallProgress = () => {
        const answered = questions.filter(isAnswered).length;
        const percent = questions.length > 0 ? (answered / questions.length) * 100 : 0;
        if (els.overallFill) els.overallFill.style.width = `${percent}%`;
    };

    const computeTimingPayload = (question) => {
        const startedAt = question.answer?.question_started_at || nowIso();
        const answeredAt = nowIso();
        const durationSeconds = Math.max(0, Math.round((toTimestamp(answeredAt) - toTimestamp(startedAt)) / 1000));
        const ideal = Number(question.ideal_time_seconds || 90);

        question.answer = question.answer || {};
        question.answer.question_started_at = startedAt;
        question.answer.answered_at = answeredAt;
        question.answer.answer_duration_seconds = durationSeconds;
        question.answer.answered_on_time = durationSeconds <= ideal;

        return {
            question_started_at: startedAt,
            answered_at: answeredAt,
            ideal_time_seconds: ideal,
            answer_duration_seconds: durationSeconds,
            answered_on_time: durationSeconds <= ideal,
        };
    };

    const scheduleSave = (question, payload) => {
        if (isLocked) return;

        state.pendingByQuestion.set(question.id, true);
        updateAutosaveLabel();

        if (state.saveTimers.has(question.id)) {
            clearTimeout(state.saveTimers.get(question.id));
        }

        const timeout = setTimeout(async () => {
            state.savingByQuestion.set(question.id, true);
            updateAutosaveLabel();

            try {
                const response = await fetch(saveTemplate.replace('__QUESTION__', String(question.id)), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) throw new Error('Autosave failed');

                state.pendingByQuestion.set(question.id, false);
            } catch (error) {
                state.pendingByQuestion.set(question.id, true);
                els.autosave.textContent = 'Save failed, retrying...';
            } finally {
                state.savingByQuestion.set(question.id, false);
                updateAutosaveLabel();
                updateOverallProgress();
            }
        }, 450);

        state.saveTimers.set(question.id, timeout);
    };

    const updateTimerBar = () => {
        const question = questions[state.currentIndex];
        if (!question || !els.timerFill || !els.timerPill) return;

        if (!question.answer?.question_started_at) {
            question.answer = question.answer || {};
            question.answer.question_started_at = nowIso();
            scheduleSave(question, { question_started_at: question.answer.question_started_at, ideal_time_seconds: Number(question.ideal_time_seconds || 90) });
        }

        const started = toTimestamp(question.answer.question_started_at);
        const elapsedSeconds = started ? Math.max(0, Math.round((Date.now() - started) / 1000)) : 0;
        const ideal = Number(question.ideal_time_seconds || 90);
        const progress = Math.min(100, (elapsedSeconds / ideal) * 100);

        els.timerFill.style.width = `${progress}%`;
        els.timerPill.textContent = `Ideal ${ideal}s · Elapsed ${elapsedSeconds}s`;
        els.timerFill.classList.toggle('timer-late', elapsedSeconds > ideal);
    };

    const renderQuestion = () => {
        const question = questions[state.currentIndex];
        if (!question) return;

        if (state.timerInterval) {
            clearInterval(state.timerInterval);
        }

        els.counter.textContent = `Question ${state.currentIndex + 1} of ${questions.length}`;

        let body = `
            <div class="row-between">
                <strong>${question.type.toUpperCase()} Question</strong>
                <span class="pill">${Number(question.marks).toFixed(2)} marks</span>
            </div>
            <p style="margin:0;font-size:1.03rem;line-height:1.6">${question.question_text}</p>
        `;

        if (question.type === 'mcq') {
            const optionsHtml = (question.options || []).map((option) => {
                const checked = Number(question.answer?.selected_option_id) === Number(option.id) ? 'checked' : '';
                return `
                    <label class="quiz-option">
                        <input type="radio" name="mcq_option" value="${option.id}" ${checked} ${isLocked ? 'disabled' : ''}>
                        <span><strong>${option.option_key}.</strong> ${option.option_text}</span>
                    </label>
                `;
            }).join('');

            body += `<div class="stack-md">${optionsHtml}</div>`;
        } else {
            const safeText = (question.answer?.answer_text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            body += `
                <label class="field" style="margin:0">
                    <span>Your theory answer</span>
                    <textarea id="theory-answer" rows="8" placeholder="Write your response here..." ${isLocked ? 'disabled' : ''}>${safeText}</textarea>
                </label>
            `;
        }

        els.panel.innerHTML = body;

        if (question.type === 'mcq' && !isLocked) {
            els.panel.querySelectorAll('input[name="mcq_option"]').forEach((input) => {
                input.addEventListener('change', (event) => {
                    question.answer = question.answer || {};
                    question.answer.selected_option_id = Number(event.target.value);
                    scheduleSave(question, {
                        selected_option_id: Number(event.target.value),
                        ...computeTimingPayload(question),
                    });
                    updateOverallProgress();
                });
            });
        }

        if (question.type === 'theory' && !isLocked) {
            const textarea = document.getElementById('theory-answer');
            textarea?.addEventListener('input', (event) => {
                question.answer = question.answer || {};
                question.answer.answer_text = event.target.value;
                scheduleSave(question, {
                    answer_text: event.target.value,
                    ...computeTimingPayload(question),
                });
                updateOverallProgress();
            });
        }

        els.prev.disabled = state.currentIndex === 0;
        els.next.disabled = state.currentIndex >= questions.length - 1;

        updateAutosaveLabel();
        updateTimerBar();
        state.timerInterval = setInterval(updateTimerBar, 1000);
    };

    els.prev?.addEventListener('click', () => {
        if (state.currentIndex > 0) {
            state.currentIndex -= 1;
            renderQuestion();
        }
    });

    els.next?.addEventListener('click', () => {
        if (state.currentIndex < questions.length - 1) {
            state.currentIndex += 1;
            renderQuestion();
        }
    });

    els.submitForm?.addEventListener('submit', (event) => {
        const answeredCount = questions.filter(isAnswered).length;
        const confirmed = window.confirm(`Submit quiz now? You answered ${answeredCount} of ${questions.length} questions.`);
        if (!confirmed) event.preventDefault();
    });

    updateOverallProgress();
    renderQuestion();
})();
</script>
@endif
@endsection
