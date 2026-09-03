import './bootstrap';
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
    const palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e'];
    const progressCanvas = document.getElementById('budget-progress-chart');

    if (progressCanvas) {
        new Chart(progressCanvas, {
            type: 'bar',
            data: { labels: ['Pagu RKAS'], datasets: [
                { label: 'Realisasi', data: [data.realization], backgroundColor: '#2563eb', borderRadius: 8, borderSkipped: false },
                { label: 'Sisa', data: [data.remaining], backgroundColor: '#dbeafe', borderRadius: 8, borderSkipped: false },
            ] },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { stacked: true, grid: { color: '#f1f5f9' }, ticks: { callback: money } }, y: { stacked: true, grid: { display: false } } }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: (context) => context.dataset.label + ': ' + money(context.parsed.x) } } } },
        });
    }

    const compositionCanvas = document.getElementById('spending-composition-chart');
    if (compositionCanvas && data.categories.length) {
        new Chart(compositionCanvas, {
            type: 'doughnut',
            data: { labels: data.categories.map((category) => category.label), datasets: [{ data: data.categories.map((category) => category.amount), backgroundColor: palette, borderColor: '#ffffff', borderWidth: 4, hoverOffset: 7 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => context.label + ': ' + money(context.parsed) } } } },
        });
    }

    const hierarchyEl = document.getElementById('hierarchy-chart-data');
    const hierarchyCanvas = document.getElementById('hierarchy-bar-chart');
    if (hierarchyEl && hierarchyCanvas) {
        const hData = JSON.parse(hierarchyEl.textContent);
        const hMoney = (v)=> new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(v);
        new Chart(hierarchyCanvas, {
            type: 'bar',
            data: {
                labels: hData.map(d=> d.code),
                datasets: [
                    { label: 'Pagu', data: hData.map(d=> d.budget), backgroundColor: '#6366f1', borderRadius: 6 },
                    { label: 'Realisasi', data: hData.map(d=> d.realization), backgroundColor: '#10b981', borderRadius: 6 },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { grid: { display:false }, ticks: { maxRotation: 0, font:{size:10} } },
                    y: { grid: { color:'#f1f5f9' }, ticks: { callback: hMoney } }
                },
                plugins: { legend: { position:'bottom', labels:{usePointStyle:true, boxWidth:8} }, tooltip:{ callbacks:{ label:(c)=> c.dataset.label+': '+hMoney(c.parsed.y), title:(items)=> { const d=hData[items[0].dataIndex]; return d.code+' — '+d.name; } } } }
            }
        });
    }
}

const initializeHtmlTablePagination = (root = document) => {
    root.querySelectorAll('main table:not([data-table-pagination-ready])').forEach((table) => {
        table.dataset.tablePaginationReady = 'true';

        const body = table.tBodies[0];
        if (!body || table.dataset.pagination === 'none' || table.dataset.pagination === 'server') {
            return;
        }

        const container = table.closest('section, [data-table-container], [wire\\:id]') || table.parentElement?.parentElement;
        const hasServerPagination = table.closest('[wire\\:id]')
            || container?.querySelector('nav[role="navigation"][aria-label*="Pagination"], nav[aria-label="Pagination"]');
        const rows = Array.from(body.rows);

        if (hasServerPagination || rows.length <= 10) {
            return;
        }

        let currentPage = 1;
        let pageSize = Number(table.dataset.pageSize || 10);
        const pagination = document.createElement('div');
        pagination.className = 'app-table-pagination';
        pagination.dataset.clientPagination = 'true';
        pagination.innerHTML = `
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <span class="app-table-pagination-summary"></span>
                <label class="flex items-center gap-2">Baris
                    <select aria-label="Jumlah baris per halaman">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-page-action="previous" aria-label="Halaman sebelumnya">← Sebelumnya</button>
                <span class="app-table-pagination-page min-w-20 text-center text-sm font-bold text-slate-700"></span>
                <button type="button" data-page-action="next" aria-label="Halaman berikutnya">Berikutnya →</button>
            </div>`;

        const wrapper = table.parentElement;
        wrapper?.insertAdjacentElement('afterend', pagination);
        const summary = pagination.querySelector('.app-table-pagination-summary');
        const pageLabel = pagination.querySelector('.app-table-pagination-page');
        const previous = pagination.querySelector('[data-page-action="previous"]');
        const next = pagination.querySelector('[data-page-action="next"]');
        const pageSizeSelect = pagination.querySelector('select');
        pageSizeSelect.value = String(pageSize);

        const render = () => {
            const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const start = (currentPage - 1) * pageSize;
            const end = Math.min(start + pageSize, rows.length);

            rows.forEach((row, index) => {
                const visible = index >= start && index < end;
                row.hidden = !visible;
                row.classList.remove('app-table-row-odd', 'app-table-row-even');
                if (visible) {
                    row.classList.add((index - start) % 2 === 0 ? 'app-table-row-odd' : 'app-table-row-even');
                }
            });

            summary.textContent = `Menampilkan ${start + 1}–${end} dari ${rows.length}`;
            pageLabel.textContent = `${currentPage} / ${pageCount}`;
            previous.disabled = currentPage === 1;
            next.disabled = currentPage === pageCount;
        };

        previous.addEventListener('click', () => { currentPage -= 1; render(); });
        next.addEventListener('click', () => { currentPage += 1; render(); });
        pageSizeSelect.addEventListener('change', () => {
            pageSize = Number(pageSizeSelect.value);
            currentPage = 1;
            render();
        });
        render();
    });
};

const initializeNativeDateInputs = (root = document) => {
    const selector = 'input[type="date"]';
    const inputs = [
        ...(root instanceof Element && root.matches(selector) ? [root] : []),
        ...root.querySelectorAll(selector),
    ];

    inputs.forEach((input) => {
        input.lang = 'id';
        input.placeholder = 'dd/mm/yyyy';
        input.title = 'Masukkan tanggal dengan urutan hari/bulan/tahun';
    });
};

initializeHtmlTablePagination();
initializeNativeDateInputs();

const tableObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) {
            initializeHtmlTablePagination(node.matches('table') ? node.parentElement || node : node);
            initializeNativeDateInputs(node);
        }
    }));
});
tableObserver.observe(document.querySelector('main') || document.body, { childList: true, subtree: true });
