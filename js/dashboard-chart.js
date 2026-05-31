(function () {
    const data = window.MT_DASHBOARD_CHART;
    if (!data || typeof Chart === 'undefined') {
        return;
    }

    let chartInstance = null;

    function readCssVar(name, fallback) {
        const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function themeColors() {
        return {
            text: readCssVar('--text', '#1a2230'),
            muted: readCssVar('--muted', '#5a6578'),
            border: readCssVar('--border', '#d5dbe8'),
            primary: readCssVar('--primary', '#1d4ed8'),
            surface: readCssVar('--surface', '#ffffff'),
        };
    }

    function formatEuro(n) {
        return (
            Number(n).toLocaleString('it-IT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }) + ' €'
        );
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function renderBreakdown(period) {
        // La lista sotto il grafico usa gli stessi dati del canvas, cosi' non racconta due storie diverse.
        const root = document.getElementById('chart-breakdown');
        if (!root) {
            return;
        }
        const cfg = data[period];
        const rows = cfg && cfg.breakdown ? cfg.breakdown : [];
        if (rows.length === 0) {
            root.innerHTML = '<p class="muted small">Nessuna spesa in questo periodo.</p>';
            return;
        }
        const parts = ['<ul class="category-list compact" aria-label="Riparto per categoria">'];
        rows.forEach(function (r) {
            const slug = esc(r.slug);
            const cat = esc(r.categoria);
            const pct = esc(String(r.pct).replace('.', ','));
            const bar = Math.min(100, Math.max(0, Number(r.bar) || 0));
            parts.push(
                '<li class="category-row cat-' +
                    slug +
                    '">' +
                    '<div class="category-row-head">' +
                    '<span class="badge-cat badge-cat--' +
                    slug +
                    '">' +
                    cat +
                    '</span>' +
                    '<span class="category-amount">' +
                    formatEuro(r.tot) +
                    '</span>' +
                    '</div>' +
                    '<div class="category-meta muted small">' +
                    pct +
                    '% del periodo</div>' +
                    '<div class="mini-bar-track" role="presentation">' +
                    '<div class="mini-bar-fill mini-bar-fill--' +
                    slug +
                    '" style="width:' +
                    bar +
                    '%;"></div>' +
                    '</div>' +
                    '</li>'
            );
        });
        parts.push('</ul>');
        root.innerHTML = parts.join('');
    }

    function updateDesc(period) {
        const el = document.getElementById('chart-period-desc');
        if (!el) {
            return;
        }
        const cfg = data[period];
        if (!cfg) {
            el.textContent = '';
            return;
        }
        const total = cfg.series && typeof cfg.series.total === 'number' ? cfg.series.total : 0;
        el.textContent =
            cfg.periodTitle + ' · ' + cfg.periodRange + ' · totale periodo: ' + formatEuro(total);
    }

    function buildChart(period) {
        const canvas = document.getElementById('chart-spese');
        if (!canvas) {
            return;
        }
        const cfg = data[period];
        if (!cfg || !cfg.series) {
            return;
        }
        const c = themeColors();
        const labels = cfg.series.labels || [];
        const values = cfg.series.values || [];

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        chartInstance = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Spesa (€)',
                        data: values,
                        borderColor: c.primary,
                        backgroundColor: 'transparent',
                        tension: 0.25,
                        fill: false,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: { color: c.text },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return formatEuro(ctx.parsed.y);
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: c.muted, maxRotation: 45, minRotation: 0 },
                        grid: { color: c.border },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: c.muted,
                            callback: function (val) {
                                return Number(val).toLocaleString('it-IT');
                            },
                        },
                        grid: { color: c.border },
                    },
                },
            },
        });
    }

    function refresh(period) {
        updateDesc(period);
        buildChart(period);
        renderBreakdown(period);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const sel = document.getElementById('chart-period');
        if (!sel) {
            return;
        }

        sel.addEventListener('change', function () {
            refresh(sel.value);
        });

        const themeBtn = document.getElementById('theme-toggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                window.setTimeout(function () {
                    refresh(sel.value);
                }, 0);
            });
        }

        refresh(sel.value);
    });
})();
