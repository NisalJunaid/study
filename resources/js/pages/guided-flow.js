const parseStep = (value, max) => {
    const parsed = Number.parseInt(value, 10);
    if (!Number.isFinite(parsed)) return 1;
    return Math.max(1, Math.min(parsed, max));
};

export const initGuidedFlow = ({
    root,
    initialStep = 1,
    onStepChange = () => {},
    validateStep = () => true,
}) => {
    if (!root) return null;

    const panes = Array.from(root.querySelectorAll('[data-guided-step]'));
    const totalSteps = panes.length;
    if (!totalSteps) return null;

    const prevButtons = Array.from(root.querySelectorAll('[data-guided-prev]'));
    const nextButtons = Array.from(root.querySelectorAll('[data-guided-next]'));
    const submitButtons = Array.from(root.querySelectorAll('[data-guided-submit]'));
    const stepInput = root.querySelector('[data-guided-current-step-input]');
    const tracker = Array.from(root.querySelectorAll('[data-guided-step-list] [data-step-index]'));

    let currentStep = parseStep(initialStep, totalSteps);

    const setStep = (step) => {
        currentStep = parseStep(step, totalSteps);

        panes.forEach((pane, index) => {
            const active = index + 1 === currentStep;
            pane.hidden = !active;
        });

        tracker.forEach((node) => {
            const idx = Number.parseInt(node.dataset.stepIndex || '', 10);
            node.classList.remove('guided-step-current', 'guided-step-complete', 'guided-step-upcoming');
            if (idx < currentStep) {
                node.classList.add('guided-step-complete');
            } else if (idx === currentStep) {
                node.classList.add('guided-step-current');
            } else {
                node.classList.add('guided-step-upcoming');
            }

            const dot = node.querySelector('.guided-step-dot');
            if (dot) dot.textContent = idx < currentStep ? '✓' : String(idx);
        });

        if (stepInput) stepInput.value = String(currentStep);

        prevButtons.forEach((btn) => {
            btn.hidden = currentStep === 1;
            btn.disabled = currentStep === 1;
        });

        nextButtons.forEach((btn) => {
            const isFinal = currentStep === totalSteps;
            btn.hidden = isFinal;
            btn.disabled = false;
        });

        submitButtons.forEach((btn) => {
            btn.hidden = currentStep !== totalSteps;
            btn.disabled = currentStep !== totalSteps;
        });

        const progress = root.querySelector('[role="progressbar"]');
        const progressValue = root.querySelector('.guided-progress-value');
        const percent = Math.round((currentStep / totalSteps) * 100);
        if (progress) progress.setAttribute('aria-valuenow', String(percent));
        if (progressValue) progressValue.style.width = `${percent}%`;

        onStepChange(currentStep);
    };

    prevButtons.forEach((btn) => btn.addEventListener('click', () => setStep(currentStep - 1)));
    nextButtons.forEach((btn) => btn.addEventListener('click', () => {
        if (!validateStep(currentStep)) return;
        setStep(currentStep + 1);
    }));

    setStep(currentStep);

    return {
        getStep: () => currentStep,
        setStep,
    };
};
