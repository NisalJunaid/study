const DEFAULT_DELAY = 2800;

const toCleanString = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    return value.trim();
};

const hasText = (value) => toCleanString(value).length > 0;

const normalizeDelay = (value) => {
    const parsed = Number(value);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : DEFAULT_DELAY;
};

const normalizePayload = (payload = {}) => {
    const primaryUrl = toCleanString(payload.primary_url || '');
    const redirectUrl = toCleanString(payload.redirect_url || primaryUrl || '');

    const normalized = {
        title: toCleanString(payload.title),
        message: toCleanString(payload.message),
        variant: toCleanString(payload.variant) || 'info',
        primaryLabel: toCleanString(payload.primary_label),
        primaryUrl: primaryUrl || null,
        secondaryLabel: toCleanString(payload.secondary_label),
        secondaryUrl: toCleanString(payload.secondary_url) || null,
        redirectUrl: redirectUrl || null,
        redirectDelay: normalizeDelay(payload.redirect_delay_ms || payload.auto_redirect_delay_ms),
        dismissible: payload.dismissible !== false,
        blocking: payload.blocking === true,
    };

    if (!hasText(normalized.primaryLabel) && normalized.redirectUrl) {
        normalized.primaryLabel = 'Continue';
    }

    if (!hasText(normalized.primaryLabel) && (hasText(normalized.title) || hasText(normalized.message))) {
        normalized.primaryLabel = 'Okay';
    }

    return normalized;
};

const isMeaningfulPayload = (payload) => hasText(payload.title)
    || hasText(payload.message)
    || hasText(payload.redirectUrl)
    || hasText(payload.primaryUrl)
    || (payload.variant === 'confirm' && hasText(payload.primaryLabel));

const hasHeadline = (payload) => hasText(payload.title) || hasText(payload.message);

const hasBlockingActionPath = (payload) => hasText(payload.primaryUrl)
    || hasText(payload.redirectUrl)
    || hasText(payload.secondaryUrl);

const isValidActionPath = (value) => {
    if (!hasText(value)) return false;

    return value.startsWith('/')
        || value.startsWith('#')
        || /^https?:\/\//i.test(value);
};

const isRenderablePayload = (payload) => {
    if (!hasHeadline(payload)) {
        return false;
    }

    if (payload.blocking && (!hasHeadline(payload) || !hasBlockingActionPath(payload))) {
        return false;
    }

    if (payload.blocking && ![payload.primaryUrl, payload.redirectUrl, payload.secondaryUrl].some(isValidActionPath)) {
        return false;
    }

    if (!isMeaningfulPayload(payload)) {
        return false;
    }

    return true;
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
    const iconEl = container.querySelector('[data-overlay-icon]');

    const state = {
        open: false,
        redirectTimer: null,
        interval: null,
        resolve: null,
    };

    const enforceHiddenState = () => {
        if (state.open) return;
        container.hidden = true;
        container.classList.remove('is-open');
        container.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overlay-open');
    };

    const clearTimers = () => {
        window.clearTimeout(state.redirectTimer);
        window.clearInterval(state.interval);
        state.redirectTimer = null;
        state.interval = null;
    };

    const resetVisuals = () => {
        if (titleEl) titleEl.textContent = '';
        if (messageEl) messageEl.textContent = '';
        if (countdownEl) {
            countdownEl.textContent = '';
            countdownEl.hidden = true;
        }

        if (primaryButton) {
            primaryButton.hidden = true;
            primaryButton.onclick = null;
            primaryButton.textContent = 'Continue';
        }

        if (secondaryButton) {
            secondaryButton.hidden = true;
            secondaryButton.onclick = null;
            secondaryButton.textContent = 'Cancel';
        }

        if (dismissButton) {
            dismissButton.hidden = false;
            dismissButton.onclick = null;
        }
    };

    const close = (result = false) => {
        clearTimers();
        enforceHiddenState();
        delete container.dataset.variant;
        delete container.dataset.blocking;
        state.open = false;
        resetVisuals();

        if (typeof state.resolve === 'function') {
            state.resolve(result);
            state.resolve = null;
        }
    };

    const overlayIcon = (variant) => {
        if (variant === 'success') return '✅';
        if (variant === 'warning') return '⚠️';
        if (variant === 'danger') return '⛔';
        if (variant === 'confirm') return '❔';
        return 'ℹ️';
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

        if (!hasText(label)) {
            button.hidden = true;
            button.onclick = null;
            return;
        }

        button.hidden = false;
        button.textContent = label;
        button.onclick = () => {
            if (hasText(url)) {
                redirectNow(url);
                return;
            }

            close(fallbackResult);
        };
    };

    const show = (payload = {}) => {
        const data = normalizePayload(payload);

        if (!isRenderablePayload(data)) {
            close(false);
            return false;
        }

        clearTimers();
        resetVisuals();

        container.dataset.variant = data.variant;
        container.dataset.blocking = data.blocking ? '1' : '0';
        container.hidden = false;
        container.classList.add('is-open');
        container.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overlay-open');
        state.open = true;

        if (titleEl) titleEl.textContent = data.title;
        if (messageEl) messageEl.textContent = data.message;
        if (iconEl) iconEl.textContent = overlayIcon(data.variant);

        const primaryActionUrl = data.primaryUrl || data.redirectUrl;
        bindActionButton(primaryButton, data.primaryLabel, primaryActionUrl, true);
        bindActionButton(secondaryButton, data.secondaryLabel, data.secondaryUrl, false);

        if (dismissButton) {
            dismissButton.hidden = data.blocking || !data.dismissible;
            dismissButton.onclick = () => {
                if (data.blocking) return;
                close(false);
            };
        }

        if (countdownEl && data.redirectUrl) {
            countdownEl.hidden = false;

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

        return true;
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
        if (dismissButton?.hidden) return;
        close(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !state.open || container.dataset.blocking === '1') return;
        close(false);
    });

    enforceHiddenState();

    return { show, close, confirm, normalizePayload, isRenderablePayload };
};

document.addEventListener('DOMContentLoaded', () => {
    const api = setupOverlay();
    if (!api) return;

    window.FocusOverlay = api;

    const root = document.querySelector('[data-global-overlay]');
    const initialPayload = root?.dataset.initialOverlay;
    if (initialPayload) {
        try {
            const parsed = JSON.parse(initialPayload);
            if (parsed && typeof parsed === 'object') {
                const normalized = api.normalizePayload(parsed);
                if (api.isRenderablePayload(normalized)) {
                    api.show(normalized);
                }
            }
        } catch {
            api.close(false);
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
