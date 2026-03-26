document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('guided-register-root');
    if (!root) {
        return;
    }

    const form = root.querySelector('[data-onboarding-form]');
    const steps = Array.from(root.querySelectorAll('[data-step]'));
    const indicators = Array.from(root.querySelectorAll('[data-step-indicator]'));
    const progressBar = root.querySelector('[data-onboarding-progress-bar]');
    const nextButton = root.querySelector('[data-next-step]');
    const prevButton = root.querySelector('[data-prev-step]');
    const submitButton = root.querySelector('[data-submit-step]');
    let currentStep = 1;

    const activateStep = (step) => {
        currentStep = Math.min(steps.length, Math.max(1, step));
        steps.forEach((section, index) => {
            section.hidden = index + 1 !== currentStep;
        });

        indicators.forEach((node, index) => {
            node.classList.toggle('active', index + 1 === currentStep);
        });

        if (progressBar) {
            progressBar.style.width = `${(currentStep / steps.length) * 100}%`;
        }

        if (prevButton) {
            prevButton.hidden = currentStep === 1;
        }

        if (nextButton) {
            nextButton.hidden = currentStep === steps.length;
        }

        if (submitButton) {
            submitButton.hidden = currentStep !== steps.length;
        }
    };

    const validateStep = (step) => {
        const section = steps[step - 1];
        if (!section) {
            return true;
        }

        const fields = Array.from(section.querySelectorAll('input,select,textarea'));
        for (const field of fields) {
            if (field.type === 'radio') {
                const grouped = section.querySelectorAll(`input[type="radio"][name="${field.name}"]`);
                if (field.required && !Array.from(grouped).some((item) => item.checked)) {
                    field.reportValidity();
                    return false;
                }
                continue;
            }

            if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }

        return true;
    };

    nextButton?.addEventListener('click', () => {
        if (!validateStep(currentStep)) {
            return;
        }

        activateStep(currentStep + 1);
    });

    prevButton?.addEventListener('click', () => activateStep(currentStep - 1));

    indicators.forEach((indicator) => {
        indicator.addEventListener('click', () => {
            const target = Number(indicator.dataset.stepIndicator || 1);
            if (target <= currentStep || validateStep(currentStep)) {
                activateStep(target);
            }
        });
    });

    form?.addEventListener('submit', (event) => {
        if (!validateStep(currentStep)) {
            event.preventDefault();
        }
    });

    const formatCurrency = (currency, amount) => `${currency} ${Number(amount || 0).toFixed(2)}`;
    const planInputs = Array.from(root.querySelectorAll('[data-plan-picker]'));

    const summaryPlan = root.querySelector('[data-summary-plan]');
    const summaryBase = root.querySelector('[data-summary-base]');
    const summaryProrated = root.querySelector('[data-summary-prorated]');
    const summaryRegistration = root.querySelector('[data-summary-registration]');
    const summaryTotal = root.querySelector('[data-summary-total]');

    const syncPlanState = () => {
        const selected = planInputs.find((input) => input.checked);

        planInputs.forEach((input) => {
            const card = input.closest('[data-plan-card]');
            card?.classList.toggle('active', input.checked);
        });

        if (!selected) {
            return;
        }

        const currency = selected.dataset.currency || 'USD';
        const planName = selected.dataset.planName || 'Selected plan';
        const planLabel = selected.dataset.planLabel || '';
        const base = selected.dataset.base;
        const prorated = selected.dataset.prorated;
        const registration = selected.dataset.registration;
        const total = selected.dataset.total;

        if (summaryPlan) summaryPlan.textContent = `${planName} (${planLabel})`;
        if (summaryBase) summaryBase.textContent = formatCurrency(currency, base);
        if (summaryProrated) summaryProrated.textContent = formatCurrency(currency, prorated);
        if (summaryRegistration) summaryRegistration.textContent = formatCurrency(currency, registration);
        if (summaryTotal) summaryTotal.textContent = formatCurrency(currency, total);
    };

    planInputs.forEach((input) => input.addEventListener('change', syncPlanState));
    syncPlanState();

    const uploadPreviews = Array.from(root.querySelectorAll('[data-upload-preview]'));

    const bindUploadPreview = (container) => {
        const input = container.querySelector('[data-upload-input]');
        const fileName = container.querySelector('[data-upload-filename]');
        const image = container.querySelector('[data-upload-image]');
        const fallback = container.querySelector('[data-upload-fallback]');
        const placeholder = container.querySelector('[data-upload-placeholder]');

        const update = () => {
            const file = input?.files?.[0];
            container.classList.toggle('has-file', Boolean(file));

            if (!file) {
                if (fileName) fileName.textContent = 'No file selected yet.';
                if (placeholder) placeholder.hidden = false;
                if (fallback) fallback.hidden = true;
                if (image) {
                    image.hidden = true;
                    image.removeAttribute('src');
                }

                return;
            }

            if (fileName) fileName.textContent = file.name;
            if (placeholder) placeholder.hidden = true;

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    if (image) {
                        image.src = event.target?.result;
                        image.hidden = false;
                    }
                    if (fallback) fallback.hidden = true;
                };
                reader.readAsDataURL(file);
            } else {
                if (image) {
                    image.hidden = true;
                    image.removeAttribute('src');
                }
                if (fallback) fallback.hidden = false;
            }
        };

        input?.addEventListener('change', update);
        update();
    };

    uploadPreviews.forEach(bindUploadPreview);

    const copyButton = root.querySelector('[data-copy-account]');
    const copyFeedback = root.querySelector('[data-copy-feedback]');
    const bankAccount = root.querySelector('[data-bank-account]');

    copyButton?.addEventListener('click', async () => {
        const text = bankAccount?.textContent?.trim();
        if (!text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            if (copyFeedback) {
                copyFeedback.textContent = 'Copied account number.';
                copyFeedback.hidden = false;
                window.setTimeout(() => {
                    copyFeedback.hidden = true;
                }, 1500);
            }
        } catch (error) {
            if (copyFeedback) {
                copyFeedback.textContent = 'Copy failed. Please copy manually.';
                copyFeedback.hidden = false;
            }
        }
    });

    activateStep(root.querySelector('.field-error') ? 1 : 1);
});
