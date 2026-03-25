const CURSOR_SMOKE_CONFIG = {
    maxParticles: 12,
    minDistance: 26,
    spawnIntervalMs: 48,
    idleOpacity: 0,
    activeOpacity: 1,
};

const shouldDisableSmokeEffect = () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return true;
    }

    if (window.matchMedia('(pointer: coarse)').matches) {
        return true;
    }

    return false;
};

const initSmokyCursorEffect = () => {
    const layer = document.querySelector('[data-smoky-cursor-layer]');
    const glow = document.querySelector('[data-smoky-cursor-glow]');

    if (!layer || !glow || shouldDisableSmokeEffect()) {
        if (layer) {
            layer.classList.add('is-inactive');
        }
        return;
    }

    const compactMode = window.matchMedia('(max-width: 900px)').matches;
    const config = {
        ...CURSOR_SMOKE_CONFIG,
        maxParticles: compactMode ? 7 : CURSOR_SMOKE_CONFIG.maxParticles,
        minDistance: compactMode ? 34 : CURSOR_SMOKE_CONFIG.minDistance,
    };

    let rafId = null;
    let pendingPoint = null;
    let lastSpawnTime = 0;
    let lastSpawnPoint = null;

    const setGlowPosition = ({ x, y }) => {
        glow.style.left = `${x}px`;
        glow.style.top = `${y}px`;
    };

    const spawnSplash = ({ x, y }) => {
        const splash = document.createElement('span');
        splash.className = 'smoky-cursor-splash';

        const sizeBase = compactMode ? 72 : 96;
        const jitter = Math.random() * (compactMode ? 30 : 42);
        splash.style.setProperty('--size', `${sizeBase + jitter}px`);
        splash.style.left = `${x}px`;
        splash.style.top = `${y}px`;

        splash.addEventListener('animationend', () => splash.remove(), { once: true });
        layer.appendChild(splash);

        if (layer.childElementCount > config.maxParticles + 1) {
            layer.querySelector('.smoky-cursor-splash')?.remove();
        }
    };

    const distanceBetween = (a, b) => {
        if (!a || !b) return Number.POSITIVE_INFINITY;
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        return Math.hypot(dx, dy);
    };

    const renderFrame = (timestamp) => {
        if (!pendingPoint) {
            rafId = null;
            return;
        }

        setGlowPosition(pendingPoint);
        layer.classList.remove('is-inactive');
        glow.style.opacity = String(CURSOR_SMOKE_CONFIG.activeOpacity);

        const elapsed = timestamp - lastSpawnTime;
        const movedEnough = distanceBetween(pendingPoint, lastSpawnPoint) >= config.minDistance;

        if (elapsed >= config.spawnIntervalMs && movedEnough) {
            spawnSplash(pendingPoint);
            lastSpawnTime = timestamp;
            lastSpawnPoint = { ...pendingPoint };
        }

        rafId = null;
    };

    const queueFrame = (event) => {
        pendingPoint = { x: event.clientX, y: event.clientY };
        if (!rafId) {
            rafId = window.requestAnimationFrame(renderFrame);
        }
    };

    const fadeInactive = () => {
        glow.style.opacity = String(CURSOR_SMOKE_CONFIG.idleOpacity);
        layer.classList.add('is-inactive');
    };

    window.addEventListener('pointermove', queueFrame, { passive: true });
    window.addEventListener('pointerdown', queueFrame, { passive: true });
    window.addEventListener('pointerleave', fadeInactive);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            fadeInactive();
        }
    });

    fadeInactive();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSmokyCursorEffect, { once: true });
} else {
    initSmokyCursorEffect();
}

export { initSmokyCursorEffect };
