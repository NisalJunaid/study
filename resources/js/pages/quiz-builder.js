import { initGuidedFlow } from './guided-flow.js';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('guided-quiz-setup');
    if (!root) return;

    const form = root.querySelector('#guided-quiz-form');
    if (!form) return;

    const multiModeInput = root.querySelector('#multi-subject-mode');
    const subjectCards = Array.from(root.querySelectorAll('.subject-option'));
    const levelCards = Array.from(root.querySelectorAll('[data-level-option]'));
    const levelInputs = Array.from(root.querySelectorAll('input[name="levels[]"]'));
    const topicGroups = Array.from(root.querySelectorAll('.topic-group'));
    const sharedTopicSearch = root.querySelector('#shared-topic-search');
    const summary = root.querySelector('[data-quiz-summary]');

    const modeField = form.querySelector('select[name="mode"]');
    const questionCountField = form.querySelector('input[name="question_count"]');
    const difficultyField = form.querySelector('select[name="difficulty"]');

    const isMulti = () => !!multiModeInput?.checked;

    const selectedLevels = () => levelCards
        .filter((card) => card.classList.contains('active'))
        .map((card) => card.dataset.levelValue);

    const selectedSubjectIds = () => subjectCards
        .filter((card) => card.querySelector('.subject-picker')?.checked)
        .map((card) => Number(card.querySelector('.subject-picker')?.dataset.subjectId));

    const selectedSubjects = () => subjectCards
        .filter((card) => card.querySelector('.subject-picker')?.checked)
        .map((card) => card.querySelector('.subject-picker')?.dataset.subjectName || 'Unknown');

    const selectedTopics = () => Array.from(root.querySelectorAll('.topic-chip input[type="checkbox"]:checked'))
        .map((input) => input.closest('.topic-chip')?.querySelector('span')?.textContent?.trim())
        .filter(Boolean);

    const syncLevelCards = () => {
        const levels = selectedLevels();
        const levelSet = new Set(levels);

        levelInputs.forEach((input) => {
            input.checked = levelSet.has(input.value);
            input.disabled = !input.checked;
        });

        subjectCards.forEach((card) => {
            const cardLevel = card.dataset.subjectLevel;
            const allowed = levelSet.has(cardLevel);
            card.style.display = allowed ? 'grid' : 'none';
            const picker = card.querySelector('.subject-picker');
            if (!allowed && picker) {
                picker.checked = false;
                card.classList.remove('active');
            }
        });
    };

    const syncSubjectInputNames = () => {
        subjectCards.forEach((card) => {
            if (card.style.display === 'none') return;
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

    const applySharedTopicSearch = () => {
        const query = (sharedTopicSearch?.value || '').trim().toLowerCase();

        topicGroups.forEach((group) => {
            if (group.style.display === 'none') return;

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
                const emptyMessage = group.querySelector('[data-topic-empty-message]');
                if (emptyMessage) emptyMessage.style.display = 'none';
            }
        });

        applySharedTopicSearch();
    };

    const refreshSubjects = () => {
        subjectCards.forEach((card) => {
            const input = card.querySelector('.subject-picker');
            card.classList.toggle('active', !!input?.checked);
        });
        syncSubjectInputNames();
        syncTopicGroups();
        refreshSummary();
    };

    const refreshSummary = () => {
        if (!summary) return;

        const difficultyValue = difficultyField?.value || 'All difficulties';
        summary.innerHTML = `
            <div class="stack-sm">
                <p class="mb-0"><strong>Levels:</strong> ${selectedLevels().join(', ') || 'None selected'}</p>
                <p class="mb-0"><strong>Subjects:</strong> ${selectedSubjects().join(', ') || 'None selected'}</p>
                <p class="mb-0"><strong>Topics:</strong> ${selectedTopics().join(', ') || 'All topics in selected subjects'}</p>
                <p class="mb-0"><strong>Mode:</strong> ${modeField?.selectedOptions?.[0]?.textContent || 'Mixed'}</p>
                <p class="mb-0"><strong>Questions:</strong> ${questionCountField?.value || '0'}</p>
                <p class="mb-0"><strong>Difficulty:</strong> ${difficultyValue === '' ? 'Mixed / All' : difficultyValue}</p>
            </div>
        `;
    };

    const showStepError = (step, message) => {
        const box = root.querySelector(`[data-step-error="${step}"]`);
        if (!box) return;
        box.textContent = message;
        box.hidden = false;
    };

    const clearStepError = (step) => {
        const box = root.querySelector(`[data-step-error="${step}"]`);
        if (!box) return;
        box.textContent = '';
        box.hidden = true;
    };

    const validateStep = (step) => {
        clearStepError(step);

        if (step === 1 && selectedLevels().length === 0) {
            showStepError(1, 'Select at least one level to continue.');
            return false;
        }

        if (step === 2 && selectedSubjectIds().length === 0) {
            showStepError(2, 'Choose at least one subject to continue.');
            return false;
        }

        if (step === 4) {
            const count = Number.parseInt(questionCountField?.value || '0', 10);
            if (!count || count < 1) {
                showStepError(4, 'Question count must be at least 1.');
                return false;
            }
        }

        return true;
    };

    levelCards.forEach((card) => {
        card.addEventListener('click', () => {
            const current = card.classList.contains('active');
            if (isMulti()) {
                card.classList.toggle('active', !current);
            } else {
                levelCards.forEach((node) => node.classList.remove('active'));
                card.classList.add('active');
            }
            syncLevelCards();
            refreshSubjects();
        });
    });

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
            const visibleCards = subjectCards.filter((card) => card.style.display !== 'none');
            const firstChecked = visibleCards.find((card) => card.querySelector('.subject-picker')?.checked) || visibleCards[0];
            visibleCards.forEach((card) => {
                const picker = card.querySelector('.subject-picker');
                if (picker) picker.checked = card === firstChecked;
            });
        }

        refreshSubjects();
    });

    root.querySelectorAll('.topic-chip input[type="checkbox"]').forEach((input) => {
        input.addEventListener('change', () => {
            input.closest('.topic-chip')?.classList.toggle('active', input.checked);
            refreshSummary();
        });
    });

    sharedTopicSearch?.addEventListener('input', applySharedTopicSearch);
    [modeField, questionCountField, difficultyField].forEach((input) => {
        input?.addEventListener('change', refreshSummary);
    });

    const wizard = initGuidedFlow({
        root,
        initialStep: Number.parseInt(root.dataset.initialStep || '1', 10),
        validateStep,
        onStepChange: (step) => {
            clearStepError(step);
            refreshSummary();
        },
    });

    syncLevelCards();
    refreshSubjects();
    refreshSummary();

    if (!wizard) return;

    form.addEventListener('submit', (event) => {
        if (wizard.getStep() < 5) {
            event.preventDefault();
            wizard.setStep(5);
            return;
        }

        if (!validateStep(4)) {
            event.preventDefault();
            wizard.setStep(4);
        }
    });
});
