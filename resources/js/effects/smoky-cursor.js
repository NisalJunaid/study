const SPLASH_CURSOR_CONFIG = {
    intensity: 0.85,
    opacity: 0.16,
    blur: 20,
    maxBlobsDesktop: 42,
    maxBlobsMobile: 24,
    spawnSpacing: 16,
    minSpeed: 0.22,
    maxSpeed: 0.9,
    fadeDurationMs: 1600,
    trailJitter: 0.55,
    mobileReductionFactor: 0.6,
    pixelRatioCap: 1.75,
};

const shouldDisableSplashCursor = () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return true;
    }

    if (window.matchMedia('(pointer: coarse)').matches && window.matchMedia('(max-width: 640px)').matches) {
        return true;
    }

    return false;
};

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const initSmokyCursorEffect = () => {
    const layer = document.querySelector('[data-smoky-cursor-layer]');
    const canvas = document.querySelector('[data-smoky-cursor-canvas]');

    if (!layer || !canvas || shouldDisableSplashCursor()) {
        layer?.classList.add('is-inactive');
        return;
    }

    const context = canvas.getContext('2d', {
        alpha: true,
        desynchronized: true,
    });

    if (!context) {
        layer.classList.add('is-inactive');
        return;
    }

    let width = window.innerWidth;
    let height = window.innerHeight;
    let ratio = 1;
    const blobs = [];
    let pointer = { x: width / 2, y: height / 2, active: false };
    let smoothedPointer = { ...pointer };
    let lastSpawnAt = { x: pointer.x, y: pointer.y };
    let lastFrameTime = performance.now();
    let rafId = null;

    const compactMode = window.matchMedia('(max-width: 1024px)').matches;
    const reducedIntensity = compactMode ? SPLASH_CURSOR_CONFIG.mobileReductionFactor : 1;
    const maxBlobs = Math.floor(
        (compactMode ? SPLASH_CURSOR_CONFIG.maxBlobsMobile : SPLASH_CURSOR_CONFIG.maxBlobsDesktop)
            * SPLASH_CURSOR_CONFIG.intensity,
    );

    const resizeCanvas = () => {
        width = window.innerWidth;
        height = window.innerHeight;
        ratio = Math.min(window.devicePixelRatio || 1, SPLASH_CURSOR_CONFIG.pixelRatioCap);

        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;

        context.setTransform(1, 0, 0, 1, 0, 0);
        context.scale(ratio, ratio);
    };

    const distanceBetween = (a, b) => {
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        return Math.hypot(dx, dy);
    };

    const addBlob = (x, y, force = 1) => {
        if (blobs.length >= maxBlobs) {
            blobs.shift();
        }

        const life = SPLASH_CURSOR_CONFIG.fadeDurationMs * (0.78 + Math.random() * 0.4);
        const angle = Math.random() * Math.PI * 2;
        const speed = (SPLASH_CURSOR_CONFIG.minSpeed + Math.random() * SPLASH_CURSOR_CONFIG.maxSpeed)
            * force
            * reducedIntensity;

        blobs.push({
            x,
            y,
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed,
            radius: (32 + Math.random() * 54) * reducedIntensity,
            life,
            ttl: life,
            hueShift: Math.random() * 14,
        });
    };

    const spawnTrail = (x, y, pressure = 1) => {
        const jitter = SPLASH_CURSOR_CONFIG.trailJitter * (compactMode ? 14 : 20);
        addBlob(x + (Math.random() - 0.5) * jitter, y + (Math.random() - 0.5) * jitter, pressure);

        if (Math.random() > 0.56) {
            addBlob(x + (Math.random() - 0.5) * jitter * 1.1, y + (Math.random() - 0.5) * jitter * 1.1, pressure * 0.85);
        }
    };

    const pointerMove = (event) => {
        pointer = { x: event.clientX, y: event.clientY, active: true };
        layer.classList.remove('is-inactive');

        const moved = distanceBetween(pointer, lastSpawnAt);
        if (moved >= SPLASH_CURSOR_CONFIG.spawnSpacing * reducedIntensity) {
            const pressure = clamp(moved / 42, 0.45, 1.35);
            spawnTrail(pointer.x, pointer.y, pressure);
            lastSpawnAt = { x: pointer.x, y: pointer.y };
        }

        if (!rafId) {
            rafId = window.requestAnimationFrame(renderFrame);
        }
    };

    const pointerLeave = () => {
        pointer.active = false;
    };

    const drawBlob = (blob) => {
        const lifeRatio = clamp(blob.life / blob.ttl, 0, 1);
        const alpha = SPLASH_CURSOR_CONFIG.opacity * lifeRatio * reducedIntensity;
        const radius = blob.radius * (1.15 + (1 - lifeRatio) * 0.5);
        const gradient = context.createRadialGradient(blob.x, blob.y, radius * 0.16, blob.x, blob.y, radius);

        gradient.addColorStop(0, `rgba(99, 102, 241, ${alpha * 0.95})`);
        gradient.addColorStop(0.45, `rgba(56, 189, 248, ${alpha * 0.5})`);
        gradient.addColorStop(1, 'rgba(30, 41, 59, 0)');

        context.fillStyle = gradient;
        context.beginPath();
        context.arc(blob.x, blob.y, radius, 0, Math.PI * 2);
        context.fill();
    };

    const renderFrame = (timestamp) => {
        const delta = Math.min(timestamp - lastFrameTime, 34);
        lastFrameTime = timestamp;
        const deltaScale = delta / 16.6667;

        context.clearRect(0, 0, width, height);
        context.save();
        context.filter = `blur(${SPLASH_CURSOR_CONFIG.blur * reducedIntensity}px)`;
        context.globalCompositeOperation = 'screen';

        smoothedPointer.x += (pointer.x - smoothedPointer.x) * 0.2;
        smoothedPointer.y += (pointer.y - smoothedPointer.y) * 0.2;

        if (pointer.active) {
            if (Math.random() > 0.33) {
                addBlob(smoothedPointer.x, smoothedPointer.y, 0.5);
            }

            drawBlob({
                x: smoothedPointer.x,
                y: smoothedPointer.y,
                radius: 68 * reducedIntensity,
                life: 1,
                ttl: 1,
                hueShift: 0,
            });
        }

        for (let index = blobs.length - 1; index >= 0; index -= 1) {
            const blob = blobs[index];

            blob.life -= delta;
            if (blob.life <= 0) {
                blobs.splice(index, 1);
                continue;
            }

            blob.x += blob.vx * deltaScale;
            blob.y += blob.vy * deltaScale;
            blob.vx *= 0.975;
            blob.vy *= 0.975;

            drawBlob(blob);
        }

        context.restore();

        if (blobs.length === 0 && !pointer.active) {
            layer.classList.add('is-inactive');
        }

        if (blobs.length > 0 || pointer.active) {
            rafId = window.requestAnimationFrame(renderFrame);
            return;
        }

        rafId = null;
    };

    const onVisibilityChange = () => {
        if (document.hidden) {
            pointer.active = false;
            blobs.length = 0;
            context.clearRect(0, 0, width, height);
            layer.classList.add('is-inactive');
            if (rafId) {
                window.cancelAnimationFrame(rafId);
                rafId = null;
            }
            return;
        }

        lastFrameTime = performance.now();
    };

    resizeCanvas();

    window.addEventListener('resize', resizeCanvas, { passive: true });
    window.addEventListener('pointermove', pointerMove, { passive: true });
    window.addEventListener('pointerdown', pointerMove, { passive: true });
    window.addEventListener('pointerleave', pointerLeave, { passive: true });
    window.addEventListener('blur', pointerLeave);
    document.addEventListener('visibilitychange', onVisibilityChange);

    layer.classList.add('is-inactive');
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSmokyCursorEffect, { once: true });
} else {
    initSmokyCursorEffect();
}

export { initSmokyCursorEffect };
