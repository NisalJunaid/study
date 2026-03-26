import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-progress-dashboard]');
    if (!root) return;

    const readTheme = () => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';

    const configRaw = root.getAttribute('data-chart-config');
    if (!configRaw) return;

    let config;

    try {
        config = JSON.parse(configRaw);
    } catch (_error) {
        return;
    }

    const palette = () => {
        const dark = readTheme() === 'dark';

        return {
            axis: dark ? '#94a3b8' : '#475569',
            grid: dark ? 'rgba(148, 163, 184, 0.2)' : 'rgba(148, 163, 184, 0.25)',
            accent: dark ? '#818cf8' : '#4f46e5',
            accentSoft: dark ? 'rgba(129, 140, 248, 0.25)' : 'rgba(79, 70, 229, 0.16)',
            cyan: dark ? '#22d3ee' : '#0891b2',
            emerald: dark ? '#34d399' : '#059669',
            amber: dark ? '#fbbf24' : '#d97706',
            rose: dark ? '#fb7185' : '#e11d48',
        };
    };

    const chartRefs = [];

    const createChart = (selector, makeConfig) => {
        const canvas = root.querySelector(`[data-progress-chart="${selector}"]`);
        if (!canvas) return;

        const context = canvas.getContext('2d');
        if (!context) return;

        const chart = new Chart(context, makeConfig());
        chartRefs.push({ chart, makeConfig });
    };

    const withPercentScale = (max = 100) => ({
        beginAtZero: true,
        min: 0,
        max,
        ticks: {
            callback: (value) => `${value}%`,
            color: palette().axis,
        },
        grid: {
            color: palette().grid,
        },
    });

    createChart('scoreTrend', () => ({
        type: 'line',
        data: {
            labels: config.score_trend?.labels ?? [],
            datasets: [
                {
                    label: 'Score %',
                    data: config.score_trend?.values ?? [],
                    borderColor: palette().accent,
                    backgroundColor: palette().accentSoft,
                    fill: true,
                    tension: 0.3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: withPercentScale(),
                x: {
                    ticks: { color: palette().axis },
                    grid: { display: false },
                },
            },
        },
    }));

    createChart('accuracyTrend', () => ({
        type: 'line',
        data: {
            labels: config.accuracy_trend?.labels ?? [],
            datasets: [
                {
                    label: 'Accuracy %',
                    data: config.accuracy_trend?.values ?? [],
                    borderColor: palette().cyan,
                    backgroundColor: 'rgba(34, 211, 238, 0.16)',
                    fill: true,
                    tension: 0.28,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: withPercentScale(),
                x: {
                    ticks: { color: palette().axis },
                    grid: { display: false },
                },
            },
        },
    }));

    createChart('quizVolume', () => ({
        type: 'bar',
        data: {
            labels: config.quizzes_by_period?.labels ?? [],
            datasets: [
                {
                    label: 'Quizzes',
                    data: config.quizzes_by_period?.values ?? [],
                    borderRadius: 8,
                    backgroundColor: palette().emerald,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: palette().axis },
                    grid: { color: palette().grid },
                },
                x: {
                    ticks: { color: palette().axis },
                    grid: { display: false },
                },
            },
        },
    }));

    createChart('timingRatio', () => ({
        type: 'doughnut',
        data: {
            labels: config.timing_ratio?.labels ?? [],
            datasets: [
                {
                    data: config.timing_ratio?.values ?? [],
                    backgroundColor: [palette().emerald, palette().rose],
                    borderWidth: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: palette().axis },
                },
            },
        },
    }));

    createChart('subjectComparison', () => ({
        type: 'bar',
        data: {
            labels: config.subject_comparison?.labels ?? [],
            datasets: [
                {
                    label: 'Average %',
                    data: config.subject_comparison?.values ?? [],
                    backgroundColor: config.subject_comparison?.colors ?? palette().amber,
                    borderRadius: 8,
                },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: withPercentScale(),
                y: {
                    ticks: { color: palette().axis },
                    grid: { display: false },
                },
            },
        },
    }));

    const observer = new MutationObserver(() => {
        chartRefs.forEach((item) => {
            const nextConfig = item.makeConfig();
            item.chart.options = nextConfig.options;
            item.chart.data = nextConfig.data;
            item.chart.update();
        });
    });

    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    const drawer = root.querySelector('[data-activity-drawer]');
    const openButton = root.querySelector('[data-activity-drawer-open]');
    const closeButtons = Array.from(root.querySelectorAll('[data-activity-drawer-close]'));

    if (!drawer || !openButton) return;

    const closeDrawer = () => {
        drawer.setAttribute('hidden', 'hidden');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overlay-open');
    };

    const openDrawer = () => {
        drawer.removeAttribute('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overlay-open');
    };

    openButton.addEventListener('click', openDrawer);
    closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });
});
