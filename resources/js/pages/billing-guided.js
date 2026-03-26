document.addEventListener('DOMContentLoaded', () => {
    const subscriptionRoot = document.getElementById('guided-billing-subscription');
    if (subscriptionRoot) {
        const pickers = Array.from(subscriptionRoot.querySelectorAll('[data-plan-picker]'));

        const syncPlanSelection = () => {
            pickers.forEach((input) => {
                const card = input.closest('.plan-card');
                if (!card) return;
                card.classList.toggle('active', input.checked);
            });
        };

        pickers.forEach((input) => input.addEventListener('change', syncPlanSelection));
        syncPlanSelection();
    }

    const paymentRoot = document.getElementById('guided-billing-payment');
    if (!paymentRoot) {
        return;
    }

    const form = paymentRoot.querySelector('[data-slip-form]');
    const input = form?.querySelector('[data-slip-input]');
    const preview = form?.querySelector('[data-slip-preview]');
    const image = form?.querySelector('[data-slip-image]');
    const fileName = form?.querySelector('[data-slip-filename]');
    const nonImage = form?.querySelector('[data-slip-non-image]');
    const placeholder = form?.querySelector('[data-slip-placeholder]');

    const copyButton = paymentRoot.querySelector('[data-copy-account]');
    const accountNumberNode = paymentRoot.querySelector('[data-account-number]');
    const copyFeedback = paymentRoot.querySelector('[data-copy-feedback]');

    const updatePreview = () => {
        const file = input?.files?.[0];

        if (!preview || !fileName) {
            return;
        }

        preview.classList.toggle('has-file', Boolean(file));

        if (!file) {
            fileName.textContent = 'No file selected yet.';
            if (image) {
                image.hidden = true;
                image.removeAttribute('src');
            }
            if (nonImage) {
                nonImage.hidden = true;
            }
            if (placeholder) {
                placeholder.hidden = false;
            }
            return;
        }

        fileName.textContent = file.name;
        if (placeholder) {
            placeholder.hidden = true;
        }

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (event) => {
                if (image) {
                    image.src = event.target?.result;
                    image.hidden = false;
                }
                if (nonImage) {
                    nonImage.hidden = true;
                }
            };
            reader.readAsDataURL(file);
        } else {
            if (image) {
                image.hidden = true;
                image.removeAttribute('src');
            }
            if (nonImage) {
                nonImage.hidden = false;
            }
        }
    };

    input?.addEventListener('change', updatePreview);
    updatePreview();

    copyButton?.addEventListener('click', async () => {
        const accountNumber = accountNumberNode?.textContent?.trim();
        if (!accountNumber) {
            return;
        }

        try {
            await navigator.clipboard.writeText(accountNumber);
            if (copyFeedback) {
                copyFeedback.textContent = 'Copied account number.';
                copyFeedback.hidden = false;
                window.setTimeout(() => {
                    copyFeedback.hidden = true;
                }, 1800);
            }
        } catch (error) {
            if (copyFeedback) {
                copyFeedback.textContent = 'Unable to copy automatically. Please copy manually.';
                copyFeedback.hidden = false;
            }
        }
    });
});
