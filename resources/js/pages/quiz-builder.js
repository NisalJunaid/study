document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('guided-quiz-setup');
    if (!root) return;

    const multiModeInput = root.querySelector('#multi-subject-mode');
    const subjectCards = Array.from(root.querySelectorAll('.subject-option'));
    const topicGroups = Array.from(root.querySelectorAll('.topic-group'));
    const sharedTopicSearch = root.querySelector('#shared-topic-search');

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

    sharedTopicSearch?.addEventListener('input', applySharedTopicSearch);

    refreshSubjects();
});
