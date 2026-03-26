document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('quiz-take-app');
    if (!root) return;

    const questions = JSON.parse(root.dataset.questions || '[]');
    const saveTemplate = root.dataset.saveRouteTemplate;
    const interactionRoute = root.dataset.interactionRoute;
    const csrfToken = root.dataset.csrf;
    const quizLocked = root.dataset.locked === '1';

    const state = {
        currentIndex: 0,
        busy: false,
        timerInterval: null,
        lastTimer: { width: null, elapsed: null, ideal: null, tone: null },
    };
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
    let interactionTimer = null;

    const reportInteraction = (immediate = false) => {
        if (quizLocked || !interactionRoute) return;

        const send = () => {
            fetch(interactionRoute, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            }).catch(() => null);
        };

        if (immediate) {
            if (interactionTimer) {
                window.clearTimeout(interactionTimer);
                interactionTimer = null;
            }

            send();

            return;
        }

        if (interactionTimer) window.clearTimeout(interactionTimer);
        interactionTimer = window.setTimeout(send, 400);
    };

    const structuredAnswers = (question) => {
        if (!question.answer) question.answer = {};
        if (!question.answer.answer_json || typeof question.answer.answer_json !== 'object') {
            question.answer.answer_json = {};
        }

        return question.answer.answer_json;
    };

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
        let tone = 'normal';

        if (elapsed > ideal) {
            tone = 'late';
        } else if (elapsed >= ideal * 0.75) {
            tone = 'warning';
        }

        if (state.lastTimer.width !== pct) {
            els.timerFill.style.width = `${pct}%`;
            state.lastTimer.width = pct;
        }

        if (state.lastTimer.tone !== tone) {
            els.timerFill.classList.remove('timer-warning', 'timer-late');
            if (tone === 'late') els.timerFill.classList.add('timer-late');
            if (tone === 'warning') els.timerFill.classList.add('timer-warning');
            state.lastTimer.tone = tone;
        }

        if (state.lastTimer.elapsed !== elapsed) {
            els.elapsedText.textContent = `Elapsed: ${elapsed}s`;
            state.lastTimer.elapsed = elapsed;
        }

        if (state.lastTimer.ideal !== ideal) {
            els.idealText.textContent = `Ideal: ${ideal}s`;
            state.lastTimer.ideal = ideal;
        }
    };

    const startTimer = () => {
        if (state.timerInterval) clearInterval(state.timerInterval);
        state.lastTimer = { width: null, elapsed: null, ideal: null, tone: null };
        updateTimerUi();
        state.timerInterval = window.setInterval(updateTimerUi, 1000);
    };

    const stopTimer = () => {
        if (!state.timerInterval) return;
        window.clearInterval(state.timerInterval);
        state.timerInterval = null;
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

    const buildStructuredPartsHtml = (question, locked) => {
        const answers = structuredAnswers(question);

        return (question.structured_parts || []).map((part) => {
            const disabled = locked ? 'disabled' : '';
            const value = sanitize(answers[String(part.id)] || '');

            return `
                <div class="card card-soft stack-sm">
                    <div class="row-between">
                        <strong>(${sanitize(part.part_label)})</strong>
                        <span class="pill">${Number(part.max_score || 0).toFixed(2)} marks</span>
                    </div>
                    <p class="mb-0" style="white-space:pre-wrap;">${sanitize(part.prompt_text || '')}</p>
                    <label class="field" style="margin:0">
                        <span>Your answer</span>
                        <textarea rows="4" data-structured-answer-id="${part.id}" placeholder="Answer this part..." ${disabled}>${value}</textarea>
                    </label>
                </div>
            `;
        }).join('');
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
        } else if (question.type === 'structured_response') {
            html += `<div class="stack-md">${buildStructuredPartsHtml(question, locked)}</div>`;
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
            <div class="actions-row" style="justify-content:space-between">
                <button type="button" class="btn btn-ghost" id="previous-question" ${state.currentIndex === 0 ? 'disabled' : ''}>Previous</button>
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
                    reportInteraction();
                    renderQuestion();
                });
            });
        }

        if (question.type === 'theory' && !question.locked && !quizLocked) {
            const textarea = document.getElementById('theory-answer');
            textarea?.addEventListener('input', (event) => {
                question.answer = question.answer || {};
                question.answer.answer_text = event.target.value;
                reportInteraction();
            });
        }

        if (question.type === 'structured_response' && !question.locked && !quizLocked) {
            els.panel.querySelectorAll('[data-structured-answer-id]').forEach((input) => {
                input.addEventListener('input', (event) => {
                    const answers = structuredAnswers(question);
                    answers[String(event.target.dataset.structuredAnswerId)] = event.target.value;
                    reportInteraction();
                });
            });
        }

        const previousButton = document.getElementById('previous-question');
        previousButton?.addEventListener('click', () => {
            if (state.busy || state.currentIndex === 0) return;
            reportInteraction(true);
            state.currentIndex -= 1;
            renderQuestion();
            updateSummary();
            startTimer();
        });

        const nextButton = document.getElementById('next-question');
        nextButton?.addEventListener('click', async () => {
            if (state.busy || quizLocked) return;
            reportInteraction(true);

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
            } else if (activeQuestion.type === 'structured_response') {
                const answers = structuredAnswers(activeQuestion);
                const unansweredPart = (activeQuestion.structured_parts || []).find((part) => !String(answers[String(part.id)] || '').trim());

                if (unansweredPart) {
                    overlay?.show({
                        title: 'Answer needed',
                        message: `Complete part (${unansweredPart.part_label}) before continuing.`,
                        variant: 'warning',
                        primary_label: 'Okay',
                    });
                    return;
                }

                payload.structured_answers = answers;
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
        reportInteraction(true);
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

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopTimer();
            return;
        }

        startTimer();
    });
    window.addEventListener('beforeunload', stopTimer);

    updateSummary();
    renderQuestion();
    startTimer();
});
