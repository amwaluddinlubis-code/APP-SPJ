import './bootstrap';
import '../css/forms-standardization.css';
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

const humanStatusLabels = new Map([
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
    ['REQUIRES_RECONCILIATION', 'Perlu rekonsiliasi'],
    ['RECONCILIATION', 'Perlu rekonsiliasi'],
    ['ACTIVE', 'Aktif'],
    ['INACTIVE', 'Tidak aktif'],
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

const humanizeStatusText = (value) => {
    const normalized = String(value || '').trim().toUpperCase();
    return humanStatusLabels.get(normalized) || null;
};

const initializeHumanStatuses = (root = document) => {
    const elements = [
        ...(root instanceof Element ? [root] : []),
        ...root.querySelectorAll('option, span, small, p, td, dd, button, a'),
    ];

    elements.forEach((element) => {
        if (!(element instanceof HTMLElement) || element.dataset.statusHumanized === 'true') {
            return;
        }

        if (element.children.length > 0 && element.tagName !== 'OPTION') {
            return;
        }

        const original = element.textContent?.trim();
        const humanized = humanizeStatusText(original);

        if (!humanized) {
            return;
        }

        element.textContent = humanized;
        element.dataset.statusHumanized = 'true';
        element.title ||= `Status sistem: ${String(original).trim().toUpperCase()}`;
    });
};

initializeHtmlTablePagination();
initializeNativeDateInputs();
initializeHumanStatuses();

const tableObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) {
            initializeHtmlTablePagination(node.matches('table') ? node.parentElement || node : node);
            initializeNativeDateInputs(node);
            initializeHumanStatuses(node);
        }
    }));
});
tableObserver.observe(document.querySelector('main') || document.body, { childList: true, subtree: true });

const initializeTransactionOperatorWorkspace = () => {
    const spjBuilder = document.getElementById('modul-buat-spj');
    if (!spjBuilder || spjBuilder.dataset.operatorWorkspaceReady === 'true') return;

    const workspaceRoot = spjBuilder.closest('.flex.flex-col.gap-6');
    if (!workspaceRoot) return;

    const referenceHeading = Array.from(workspaceRoot.querySelectorAll('h2'))
        .find((heading) => heading.textContent.trim() === 'Informasi Referensi');
    const sourceSection = referenceHeading?.closest('section');

    if (sourceSection) {
        sourceSection.id = 'data-arkas-bku';
        sourceSection.classList.add('relative', 'rounded-2xl', 'ring-1', 'ring-slate-200', 'scroll-mt-32');

        const sourceIntro = document.createElement('div');
        sourceIntro.className = 'mb-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 sm:px-5';
        sourceIntro.innerHTML = `
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Data ARKAS / BKU</span>
                        <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-700">Hanya dibaca</span>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-slate-800">Referensi transaksi dari sumber sinkronisasi</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Gunakan data ini sebagai pembanding. Operator tidak mengubah nilai sumber dari halaman Detail Transaksi.</p>
                </div>
                <span class="text-xs font-semibold text-slate-400">Sumber resmi transaksi</span>
            </div>`;
        sourceSection.insertAdjacentElement('beforebegin', sourceIntro);
    }

    spjBuilder.id = 'data-spj-operator';
    spjBuilder.dataset.operatorWorkspaceReady = 'true';
    spjBuilder.classList.add('ring-1', 'ring-indigo-200', 'scroll-mt-32');

    const operatorIntro = document.createElement('div');
    operatorIntro.className = 'mb-3 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 sm:px-5';
    operatorIntro.innerHTML = `
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-indigo-600 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Data SPJ Operator</span>
                    <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-indigo-700 ring-1 ring-indigo-200">Dapat diedit</span>
                </div>
                <p class="mt-2 text-sm font-semibold text-indigo-950">Lengkapi data yang akan digunakan pada dokumen SPJ</p>
                <p class="mt-1 text-xs leading-5 text-indigo-700">Isi kategori, uraian pembayaran, penerima kuitansi, metode pembayaran, dan rincian sesuai jenis SPJ.</p>
            </div>
            <span class="text-xs font-semibold text-indigo-500">Area kerja operator</span>
        </div>`;
    spjBuilder.insertAdjacentElement('beforebegin', operatorIntro);

    const heroSection = Array.from(workspaceRoot.children).find((child) =>
        child instanceof HTMLElement && child.tagName === 'SECTION' && child.querySelector('h1')
    );

    const navigator = document.createElement('nav');
    navigator.className = '-mx-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-2 shadow-sm';
    navigator.setAttribute('aria-label', 'Navigasi ruang kerja transaksi');
    navigator.innerHTML = `
        <div class="flex min-w-max items-center gap-1">
            <span class="px-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ruang kerja</span>
            <a href="#data-arkas-bku" class="rounded-lg px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">1. Data ARKAS / BKU</a>
            <a href="#data-spj-operator" class="rounded-lg bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100">2. Data SPJ Operator</a>
            <a href="#data-spj-operator" class="rounded-lg px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">3. Lengkapi & buat paket</a>
        </div>`;

    if (heroSection) {
        heroSection.insertAdjacentElement('afterend', navigator);
    } else {
        workspaceRoot.prepend(navigator);
    }

    workspaceRoot.querySelectorAll('a[href="#modul-buat-spj"]').forEach((link) => {
        link.setAttribute('href', '#data-spj-operator');
    });
};

initializeTransactionOperatorWorkspace();
document.addEventListener('livewire:navigated', initializeTransactionOperatorWorkspace);

const initializeStickyScrollTop = () => {
    if (document.getElementById('app-scroll-top-footer')) return;

    const footer = document.createElement('div');
    footer.id = 'app-scroll-top-footer';
    footer.className = 'pointer-events-none fixed inset-x-0 bottom-4 z-40 flex justify-center px-4 opacity-0 translate-y-3 transition duration-200';
    footer.setAttribute('aria-hidden', 'true');
    footer.innerHTML = `
        <button type="button"
            class="pointer-events-auto inline-flex min-h-11 items-center gap-2 rounded-full border border-slate-200 bg-white/95 px-4 py-2.5 text-sm font-bold text-slate-700 shadow-lg backdrop-blur transition hover:-translate-y-0.5 hover:bg-slate-50 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            aria-label="Kembali ke atas halaman"
            title="Kembali ke atas">
            <span class="grid h-7 w-7 place-items-center rounded-full bg-slate-900 text-base leading-none text-white" aria-hidden="true">↑</span>
            <span class="hidden sm:inline">Ke atas</span>
        </button>`;

    document.body.appendChild(footer);
    const button = footer.querySelector('button');

    const updateVisibility = () => {
        const visible = window.scrollY > 320;
        footer.classList.toggle('opacity-0', !visible);
        footer.classList.toggle('translate-y-3', !visible);
        footer.classList.toggle('opacity-100', visible);
        footer.classList.toggle('translate-y-0', visible);
        footer.setAttribute('aria-hidden', visible ? 'false' : 'true');
        button.tabIndex = visible ? 0 : -1;
    };

    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', updateVisibility, { passive: true });
    updateVisibility();
};

initializeStickyScrollTop();
document.addEventListener('livewire:navigated', initializeStickyScrollTop);
