import { initGuidedFlow } from './guided-flow.js';

document.addEventListener('DOMContentLoaded', () => {
    const subscriptionRoot = document.getElementById('guided-billing-subscription');
    if (subscriptionRoot) {
        const planGrid = subscriptionRoot.querySelector('[data-plan-grid]');
        const cards = Array.from(subscriptionRoot.querySelectorAll('[data-plan-type]'));
        const toggleButtons = Array.from(subscriptionRoot.querySelectorAll('[data-plan-toggle]'));
        const summary = subscriptionRoot.querySelector('[data-subscription-summary]');
        const pickers = Array.from(subscriptionRoot.querySelectorAll('[data-plan-picker]'));

        const updateType = (type) => {
            cards.forEach((card) => {
                card.style.display = card.dataset.planType === type ? 'block' : 'none';
            });

            toggleButtons.forEach((button) => {
                const selected = button.dataset.planToggle === type;
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
                button.classList.toggle('btn-primary', selected);
            });
        };

        const selectedPlan = () => pickers.find((input) => input.checked);

        const updateSummary = () => {
            if (!summary) return;
            const input = selectedPlan();
            if (!input) {
                summary.innerHTML = '<p class="mb-0 muted">Select a plan to continue.</p>';
                return;
            }

            const card = input.closest('.plan-card');
            const title = card?.querySelector('.h3')?.textContent?.trim() || 'Selected plan';
            const price = card?.querySelector('.plan-price')?.textContent?.trim() || '';
            const note = card?.querySelector('.text-sm')?.textContent?.trim() || '';
            summary.innerHTML = `
                <p class="mb-0"><strong>Plan:</strong> ${title}</p>
                <p class="mb-0"><strong>Price:</strong> ${price}</p>
                <p class="mb-0 muted">${note}</p>
            `;
        };

        const showStepError = (step, message) => {
            const box = subscriptionRoot.querySelector(`[data-step-error="${step}"]`);
            if (!box) return;
            box.textContent = message;
            box.hidden = false;
        };

        const clearStepError = (step) => {
            const box = subscriptionRoot.querySelector(`[data-step-error="${step}"]`);
            if (!box) return;
            box.hidden = true;
            box.textContent = '';
        };

        initGuidedFlow({
            root: subscriptionRoot,
            initialStep: Number.parseInt(subscriptionRoot.dataset.initialStep || '1', 10),
            validateStep: (step) => {
                clearStepError(step);
                if (step === 3 && !selectedPlan()) {
                    showStepError(3, 'Please select an available plan before continuing.');
                    return false;
                }
                return true;
            },
            onStepChange: () => updateSummary(),
        });

        toggleButtons.forEach((button) => {
            button.addEventListener('click', () => updateType(button.dataset.planToggle));
        });

        pickers.forEach((input) => input.addEventListener('change', updateSummary));

        const initial = toggleButtons.find((button) => button.getAttribute('aria-selected') === 'true')?.dataset.planToggle || 'monthly';
        updateType(initial);
        updateSummary();
    }

    const paymentRoot = document.getElementById('guided-billing-payment');
    if (paymentRoot) {
        const form = paymentRoot.querySelector('[data-slip-form]');
        const input = form?.querySelector('[data-slip-input]');
        const preview = form?.querySelector('[data-slip-preview]');
        const image = form?.querySelector('[data-slip-image]');
        const fileName = form?.querySelector('[data-slip-filename]');
        const nonImage = form?.querySelector('[data-slip-non-image]');
        const summaryName = form?.querySelector('[data-summary-slip-name]');

        const showStepError = (message) => {
            const box = paymentRoot.querySelector('[data-step-error="3"]');
            if (!box) return;
            box.textContent = message;
            box.hidden = false;
        };

        const clearStepError = () => {
            const box = paymentRoot.querySelector('[data-step-error="3"]');
            if (!box) return;
            box.textContent = '';
            box.hidden = true;
        };

        const updatePreview = () => {
            const file = input?.files?.[0];
            if (!file || !preview || !fileName) {
                if (preview) preview.hidden = true;
                if (summaryName) summaryName.textContent = 'No file selected';
                return;
            }

            preview.hidden = false;
            fileName.textContent = file.name;
            if (summaryName) summaryName.textContent = file.name;

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    if (image) {
                        image.src = event.target?.result;
                        image.hidden = false;
                    }
                    if (nonImage) nonImage.hidden = true;
                };
                reader.readAsDataURL(file);
            } else {
                if (image) {
                    image.hidden = true;
                    image.removeAttribute('src');
                }
                if (nonImage) nonImage.hidden = false;
            }
        };

        const wizard = initGuidedFlow({
            root: paymentRoot,
            initialStep: Number.parseInt(paymentRoot.dataset.initialStep || '1', 10),
            validateStep: (step) => {
                clearStepError();
                if (step === 3 && !input?.files?.length) {
                    showStepError('Upload your payment slip before continuing.');
                    return false;
                }
                return true;
            },
        });

        input?.addEventListener('change', () => {
            clearStepError();
            updatePreview();
        });

        form?.addEventListener('submit', (event) => {
            if (wizard && wizard.getStep() < 4) {
                event.preventDefault();
                wizard.setStep(4);
                return;
            }

            if (!input?.files?.length) {
                event.preventDefault();
                wizard?.setStep(3);
                showStepError('Upload your payment slip before submitting.');
            }
        });

        updatePreview();
    }
});
