import './bootstrap';
import '../css/forms-standardization.css';
import '../css/dark-theme-refinement.css';
import '../css/page-header-unified.css';
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';
import Chart from 'chart.js/auto';

if (!window.Alpine) {
    if (!Object.prototype.hasOwnProperty.call(Alpine, '$persist')) {
        Alpine.plugin(persist);
    }

    window.Alpine = Alpine;
    Alpine.start();
}

const chartDataElement = document.getElementById('dashboard-chart-data');

if (chartDataElement) {
    const data = JSON.parse(chartDataElement.textContent);
    const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

    const chart = new Chart(chartDataElement, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: data.datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => money(value),
                    },
                },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.dataset.label}: ${money(context.raw)}`,
                    },
                },
            },
        },
    });

    const syncChartTheme = () => {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#cbd5e1' : '#475569';
        const gridColor = isDark ? 'rgba(148, 163, 184, 0.12)' : 'rgba(148, 163, 184, 0.18)';

        chart.options.scales.x.ticks.color = textColor;
        chart.options.scales.y.ticks.color = textColor;
        chart.options.scales.x.grid.color = gridColor;
        chart.options.scales.y.grid.color = gridColor;
        chart.options.plugins.legend.labels.color = textColor;
        chart.update('none');
    };

    syncChartTheme();
    new MutationObserver(syncChartTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
}

const statusLabelMap = new Map([
    ['DRAFT', 'Belum lengkap'],
    ['BELUM_LENGKAP', 'Belum lengkap'],
    ['READY', 'Siap diproses'],
    ['SIAP', 'Siap diproses'],
    ['DISIAPKAN', 'Siap diproses'],
    ['NUMBERED', 'Sudah bernomor'],
    ['BERNOMOR', 'Sudah bernomor'],
    ['PRINTED', 'Sudah dicetak'],
    ['DICETAK', 'Sudah dicetak'],
    ['FINAL', 'Final'],
    ['ARCHIVED', 'Final'],
    ['ARSIP', 'Final'],
    ['CANCELLED', 'Dibatalkan'],
    ['CANCELED', 'Dibatalkan'],
    ['SOURCE_MISSING', 'Tidak muncul di sinkronisasi'],
    ['requires_reconciliation', 'Perlu rekonsiliasi'],
    ['DITETAPKAN', 'Sudah ditetapkan'],
    ['PENDING', 'Menunggu diproses'],
    ['PROCESSING', 'Sedang diproses'],
    ['RUNNING', 'Sedang diproses'],
    ['COMPLETED', 'Selesai'],
    ['SUCCESS', 'Selesai'],
    ['SUCCEEDED', 'Selesai'],
    ['FAILED', 'Gagal'],
    ['ERROR', 'Gagal'],
    ['LOCKED', 'Terkunci'],
    ['UNLOCKED', 'Dapat diedit'],
    ['REPLACED', 'Diganti'],
    ['GENERATED', 'Dokumen dibuat'],
]);

const replaceStatusText = (root = document.body) => {
    if (!root) return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);

    nodes.forEach((node) => {
        const value = node.nodeValue?.trim();
        if (!value || !statusLabelMap.has(value)) return;
        node.nodeValue = node.nodeValue.replace(value, statusLabelMap.get(value));
    });
};

replaceStatusText();
new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) replaceStatusText(node);
        });
    });
}).observe(document.body, { childList: true, subtree: true });

const initializeClientTablePagination = (root = document) => {
    root.querySelectorAll('table').forEach((table) => {
        if (table.dataset.paginationInitialized === 'true') return;
        if (table.dataset.pagination === 'none' || table.dataset.pagination === 'server') return;
        if (table.closest('[wire\\:id]')) return;

        const body = table.tBodies[0];
        if (!body) return;
        const rows = Array.from(body.rows);
        if (rows.length <= 10) return;

        table.dataset.paginationInitialized = 'true';
        let page = 1;
        let perPage = Number(table.dataset.perPage || 10);

        const pagination = document.createElement('div');
        pagination.className = 'app-table-pagination';
        pagination.innerHTML = `
            <div class="app-table-pagination-summary"></div>
            <div class="flex items-center gap-2">
                <button type="button" data-action="prev">Sebelumnya</button>
                <select aria-label="Baris per halaman">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" data-action="next">Berikutnya</button>
            </div>
        `;

        table.parentElement?.insertAdjacentElement('afterend', pagination);
        const summary = pagination.querySelector('.app-table-pagination-summary');
        const select = pagination.querySelector('select');
        const previous = pagination.querySelector('[data-action="prev"]');
        const next = pagination.querySelector('[data-action="next"]');
        select.value = String(perPage);

        const render = () => {
            const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
            page = Math.min(page, totalPages);
            const start = (page - 1) * perPage;
            const end = Math.min(start + perPage, rows.length);

            rows.forEach((row, index) => {
                row.hidden = index < start || index >= end;
            });

            summary.textContent = `Menampilkan ${start + 1}–${end} dari ${rows.length} baris`;
            previous.disabled = page <= 1;
            next.disabled = page >= totalPages;
        };

        previous.addEventListener('click', () => {
            page -= 1;
            render();
        });
        next.addEventListener('click', () => {
            page += 1;
            render();
        });
        select.addEventListener('change', () => {
            perPage = Number(select.value);
            page = 1;
            render();
        });

        render();
    });
};

initializeClientTablePagination();
document.addEventListener('livewire:navigated', () => initializeClientTablePagination());

const initializeScrollToTop = () => {
    if (document.getElementById('app-scroll-to-top')) return;

    const button = document.createElement('button');
    button.id = 'app-scroll-to-top';
    button.type = 'button';
    button.className = 'fixed bottom-5 left-1/2 z-40 hidden -translate-x-1/2 items-center gap-2 rounded-full border border-slate-200 bg-white/95 px-3.5 py-2 text-sm font-bold text-slate-700 shadow-lg backdrop-blur transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--theme-accent)]/30 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800';
    button.setAttribute('aria-label', 'Kembali ke atas halaman');
    button.setAttribute('title', 'Kembali ke atas');
    button.innerHTML = '<span aria-hidden="true" class="text-base leading-none">↑</span><span class="hidden sm:inline">Ke atas</span>';

    const update = () => {
        button.classList.toggle('hidden', window.scrollY <= 320);
        button.classList.toggle('flex', window.scrollY > 320);
    };

    button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    window.addEventListener('scroll', update, { passive: true });
    document.body.appendChild(button);
    update();
};

initializeScrollToTop();
document.addEventListener('livewire:navigated', initializeScrollToTop);
