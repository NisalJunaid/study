document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('quiz-take-app');
    if (!root) return;

    const questions = JSON.parse(root.dataset.questions || '[]');
    const saveTemplate = root.dataset.saveRouteTemplate;
    const csrfToken = root.dataset.csrf;
    const quizLocked = root.dataset.locked === '1';

    const state = { currentIndex: 0, busy: false, timerInterval: null };
    const overlay = window.FocusOverlay;

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
                    overlay?.show({
                        title: 'Answer needed',
                        message: 'Select an option before continuing.',
                        variant: 'warning',
                        primary_label: 'Okay',
                    });
                    return;
                }
                payload.selected_option_id = optionId;
            } else {
                const answerText = typedAnswer(activeQuestion);
                if (!answerText) {
                    overlay?.show({
                        title: 'Answer needed',
                        message: 'Write your answer before continuing.',
                        variant: 'warning',
                        primary_label: 'Okay',
                    });
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
                overlay?.show({
                    title: 'Save failed',
                    message: error.message || 'Unable to save answer.',
                    variant: 'danger',
                    primary_label: 'Try again',
                });
                nextButton.disabled = false;
                nextButton.textContent = state.currentIndex === questions.length - 1 ? 'Finish Quiz' : 'Next Question';
            }

            updateSummary();
        });
    };

    els.submitForm?.addEventListener('submit', async (event) => {
        const answeredCount = questions.filter(isAnswered).length;
        const confirmed = await (overlay?.confirm({
            title: 'Submit quiz now?',
            message: `You finalized ${answeredCount} of ${questions.length} questions.`,
            variant: 'confirm',
            primary_label: 'Submit quiz',
            secondary_label: 'Keep reviewing',
        }) ?? Promise.resolve(true));

        if (!confirmed) event.preventDefault();
    });

    const firstUnansweredIndex = questions.findIndex((q) => !isAnswered(q));
    state.currentIndex = firstUnansweredIndex === -1 ? Math.max(0, questions.length - 1) : firstUnansweredIndex;

    updateSummary();
    renderQuestion();
    startTimer();
});
