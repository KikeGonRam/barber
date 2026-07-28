const palette = ['#d4af37', '#38bdf8', '#34d399', '#a78bfa', '#f59e0b', '#fb7185', '#22d3ee', '#84cc16'];
const textColor = 'rgba(255,255,255,.48)';
const gridColor = 'rgba(255,255,255,.07)';
const numberFormatter = new Intl.NumberFormat('es-MX');

function parseConfig(canvas) {
    const node = document.getElementById(canvas.dataset.chartConfig);

    if (!node) return null;

    try {
        return JSON.parse(node.textContent);
    } catch {
        return null;
    }
}

function toValues(values) {
    return (values || []).map((value) => {
        const numeric = Number(String(value).replace(/,/g, ''));
        return Number.isFinite(numeric) ? numeric : 0;
    });
}

function shortLabel(label, mini) {
    const text = String(label ?? '').replace(/\n/g, ' · ');

    if (mini) return '';

    return text.length > 28 ? `${text.slice(0, 26)}…` : text;
}

function buildDataset(ctx, canvas, type, values, accent, mini) {
    const gradient = ctx.createLinearGradient(0, 0, 0, canvas.parentElement?.clientHeight || 240);
    gradient.addColorStop(0, `${accent}33`);
    gradient.addColorStop(1, `${accent}05`);

    const dataset = {
        data: values,
        label: 'Resultado',
        backgroundColor: values.map((_, index) => `${palette[index % palette.length]}cc`),
        borderColor: accent,
        borderWidth: 0,
        borderRadius: type === 'bar' ? 8 : 0,
        hoverBorderColor: '#ffffff',
        hoverBorderWidth: type === 'bar' ? 0 : 1,
    };

    if (type === 'line') {
        dataset.backgroundColor = gradient;
        dataset.borderColor = accent;
        dataset.borderWidth = mini ? 1.8 : 2.6;
        dataset.fill = true;
        dataset.tension = 0.42;
        dataset.pointRadius = mini ? 0 : 3;
        dataset.pointHoverRadius = 5;
    }

    if (type === 'radar') {
        dataset.backgroundColor = `${accent}24`;
        dataset.borderColor = accent;
        dataset.borderWidth = 2;
        dataset.pointBackgroundColor = accent;
        dataset.pointRadius = 2;
    }

    if (['doughnut', 'polarArea'].includes(type)) {
        dataset.borderColor = '#101010';
        dataset.borderWidth = 2;
        dataset.hoverOffset = mini ? 2 : 6;
    }

    return dataset;
}

function buildScales(type, horizontal, mini) {
    if (['doughnut', 'polarArea'].includes(type)) return {};

    if (type === 'radar') {
        return {
            r: {
                beginAtZero: true,
                angleLines: { color: gridColor },
                grid: { color: gridColor },
                pointLabels: { color: textColor, font: { size: 10, weight: '700' } },
                ticks: { display: false },
            },
        };
    }

    return {
        x: {
            display: !mini,
            beginAtZero: horizontal,
            ticks: {
                color: textColor,
                font: { size: 10, weight: '600' },
                callback(value) {
                    return shortLabel(this.getLabelForValue(value), false);
                },
            },
            grid: { color: gridColor, drawBorder: false },
        },
        y: {
            display: !mini,
            beginAtZero: !horizontal,
            ticks: {
                color: textColor,
                font: { size: 10, weight: '600' },
                callback(value) {
                    return horizontal ? shortLabel(this.getLabelForValue(value), false) : value;
                },
            },
            grid: { color: gridColor, drawBorder: false },
        },
    };
}

function chartOptions(type, labels, horizontal, mini) {
    return {
        indexAxis: horizontal ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : (mini ? 550 : 900),
            easing: 'easeOutQuart',
        },
        layout: { padding: mini ? 0 : 4 },
        plugins: {
            legend: {
                display: !mini && ['doughnut', 'polarArea'].includes(type),
                position: 'bottom',
                labels: {
                    color: 'rgba(255,255,255,.58)',
                    usePointStyle: true,
                    boxWidth: 7,
                    padding: 12,
                    font: { size: 10, weight: '700' },
                },
            },
            tooltip: {
                enabled: !mini,
                backgroundColor: '#0f0f0f',
                borderColor: 'rgba(212,175,55,.35)',
                borderWidth: 1,
                titleColor: '#d4af37',
                bodyColor: '#ffffff',
                padding: 10,
                callbacks: {
                    title(items) {
                        const index = items[0]?.dataIndex ?? 0;
                        return String(labels[index] ?? '').replace(/\n/g, ' · ');
                    },
                    label(item) {
                        return ` ${numberFormatter.format(item.raw ?? 0)}`;
                    },
                },
            },
        },
        cutout: type === 'doughnut' ? (mini ? '72%' : '64%') : undefined,
        scales: buildScales(type, horizontal, mini),
    };
}

export function registerAnalyticsCharts(Chart) {
    const instances = window.__ubAnalyticsCharts || {};
    window.__ubAnalyticsCharts = instances;

    const resizeCharts = () => {
        Object.values(instances).forEach((chart) => chart?.resize());
    };

    const bootCharts = () => {
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Figtree', sans-serif";
        Chart.defaults.color = textColor;

        document.querySelectorAll('[data-ub-analytics-chart]').forEach((canvas) => {
            const config = parseConfig(canvas);

            if (!config) return;

            if (instances[canvas.id]) {
                instances[canvas.id].resize();
                return;
            }

            const graph = config.graph || {};
            const labels = graph.labels || [];
            const values = toValues(graph.valores || []);

            if (!values.length) return;

            const mini = canvas.dataset.chartMini === 'true' || config.mini === true;
            const type = config.type === 'matrix' ? 'bar' : (config.type || graph.tipo || 'bar');
            const longLabels = labels.length > 5 || labels.some((label) => String(label).length > 18);
            const horizontal = type === 'bar' && longLabels && !mini;
            const accent = config.accent || '#d4af37';
            const ctx = canvas.getContext('2d');
            const dataset = buildDataset(ctx, canvas, type, values, accent, mini);

            instances[canvas.id] = new Chart(ctx, {
                type,
                data: {
                    labels: labels.map((label) => shortLabel(label, mini)),
                    datasets: [dataset],
                },
                options: chartOptions(type, labels, horizontal, mini),
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCharts);
    } else {
        bootCharts();
    }

    window.addEventListener('ub:analytics:resize', () => setTimeout(resizeCharts, 140));
    window.addEventListener('resize', () => setTimeout(resizeCharts, 80));
}
