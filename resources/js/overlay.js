const DEFAULT_DELAY = 2800;

const normalizePayload = (payload = {}) => {
    const redirectUrl = payload.redirect_url || payload.primary_url || null;
    const redirectDelay = Number(payload.redirect_delay_ms || payload.auto_redirect_delay_ms || DEFAULT_DELAY);

    return {
        title: payload.title || '',
        message: payload.message || '',
        variant: payload.variant || 'info',
        primaryLabel: payload.primary_label || 'Okay',
        primaryUrl: payload.primary_url || null,
        secondaryLabel: payload.secondary_label || null,
        secondaryUrl: payload.secondary_url || null,
        dismissible: payload.dismissible !== false,
        blocking: payload.blocking === true,
        redirectUrl,
        redirectDelay: Number.isFinite(redirectDelay) && redirectDelay > 0 ? redirectDelay : DEFAULT_DELAY,
    };
};

const setupOverlay = () => {
    const container = document.querySelector('[data-global-overlay]');
    if (!container) return null;

    const titleEl = container.querySelector('[data-overlay-title]');
    const messageEl = container.querySelector('[data-overlay-message]');
    const countdownEl = container.querySelector('[data-overlay-countdown]');
    const primaryButton = container.querySelector('[data-overlay-primary]');
    const secondaryButton = container.querySelector('[data-overlay-secondary]');
    const dismissButton = container.querySelector('[data-overlay-dismiss]');

    const state = {
        open: false,
        redirectTimer: null,
        interval: null,
        resolve: null,
    };

    const clearTimers = () => {
        window.clearTimeout(state.redirectTimer);
        window.clearInterval(state.interval);
        state.redirectTimer = null;
        state.interval = null;
    };

    const close = (result = false) => {
        clearTimers();
        container.hidden = true;
        container.classList.remove('is-open');
        document.body.classList.remove('overlay-open');
        state.open = false;

        if (typeof state.resolve === 'function') {
            state.resolve(result);
            state.resolve = null;
        }
    };

    const redirectNow = (url) => {
        if (!url) {
            close(true);
            return;
        }

        window.location.assign(url);
    };

    const bindActionButton = (button, label, url, fallbackResult) => {
        if (!button) return;

        if (!label) {
            button.hidden = true;
            button.onclick = null;
            return;
        }

        button.hidden = false;
        button.textContent = label;
        button.onclick = () => {
            if (url) {
                redirectNow(url);
                return;
            }

            close(fallbackResult);
        };
    };

    const show = (payload = {}) => {
        const data = normalizePayload(payload);
        clearTimers();

        container.dataset.variant = data.variant;
        container.dataset.blocking = data.blocking ? '1' : '0';
        container.hidden = false;
        container.classList.add('is-open');
        document.body.classList.add('overlay-open');
        state.open = true;

        if (titleEl) titleEl.textContent = data.title;
        if (messageEl) messageEl.textContent = data.message;

        bindActionButton(primaryButton, data.primaryLabel, data.primaryUrl || data.redirectUrl, true);
        bindActionButton(secondaryButton, data.secondaryLabel, data.secondaryUrl, false);

        if (dismissButton) {
            dismissButton.hidden = data.blocking || !data.dismissible;
            dismissButton.onclick = () => {
                if (data.blocking) return;
                close(false);
            };
        }

        if (countdownEl) {
            countdownEl.hidden = !data.redirectUrl;
            countdownEl.textContent = '';
        }

        if (data.redirectUrl && countdownEl) {
            const start = Date.now();
            const tick = () => {
                const elapsed = Date.now() - start;
                const remaining = Math.max(0, Math.ceil((data.redirectDelay - elapsed) / 1000));
                countdownEl.textContent = `Continuing in ${remaining}s…`;
            };

            tick();
            state.interval = window.setInterval(tick, 250);
            state.redirectTimer = window.setTimeout(() => redirectNow(data.redirectUrl), data.redirectDelay);
        }
    };

    const confirm = (payload = {}) => new Promise((resolve) => {
        state.resolve = resolve;
        show({
            variant: 'confirm',
            primary_label: payload.primary_label || 'Confirm',
            secondary_label: payload.secondary_label || 'Cancel',
            dismissible: true,
            ...payload,
        });
    });

    container.addEventListener('click', (event) => {
        if (event.target !== container) return;
        if (container.dataset.blocking === '1') return;
        close(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !state.open || container.dataset.blocking === '1') return;
        close(false);
    });

    return { show, close, confirm };
};

document.addEventListener('DOMContentLoaded', () => {
    const api = setupOverlay();
    if (!api) return;

    window.FocusOverlay = api;

    const root = document.querySelector('[data-global-overlay]');
    const initialPayload = root?.dataset.initialOverlay;
    if (initialPayload) {
        try {
            api.show(JSON.parse(initialPayload));
        } catch {
            // noop
        }
    }

    document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmed === '1') return;

            event.preventDefault();
            const confirmed = await api.confirm({
                title: form.dataset.confirmTitle || 'Please confirm',
                message: form.dataset.confirmMessage || 'Are you sure you want to continue?',
                variant: form.dataset.confirmVariant || 'warning',
                primary_label: form.dataset.confirmPrimary || 'Continue',
                secondary_label: form.dataset.confirmSecondary || 'Cancel',
            });

            if (!confirmed) return;
            form.dataset.confirmed = '1';
            form.requestSubmit();
        });
    });
});
