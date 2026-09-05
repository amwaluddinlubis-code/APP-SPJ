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

const findLegacyHero = (title, section) => {
    let element = title.parentElement;

    while (element && element !== section) {
        const className = typeof element.className === 'string' ? element.className : '';
        const style = element.getAttribute?.('style') || '';
        if (
            className.includes('bg-gradient')
            || className.includes('theme-header')
            || className.includes('bg-slate-900')
            || style.includes('background-image')
        ) {
            return element;
        }
        element = element.parentElement;
    }

    return section.firstElementChild instanceof HTMLElement ? section.firstElementChild : null;
};

const normalizeLegacyPageHeader = (title) => {
    if (!(title instanceof HTMLElement) || title.dataset.pageHeaderNormalized === 'true') return;
    if (title.closest('.page-header-shell')) {
        title.dataset.pageHeaderNormalized = 'true';
        return;
    }

    const section = title.closest('section');
    if (!(section instanceof HTMLElement)) return;

    const hero = findLegacyHero(title, section);
    if (!(hero instanceof HTMLElement)) return;

    section.classList.add('page-header-shell', 'page-header-legacy');
    hero.classList.add('page-header-main');
    title.classList.add('page-header-title');
    title.dataset.pageHeaderNormalized = 'true';

    const paragraphs = Array.from(hero.querySelectorAll('p'));
    const beforeTitle = paragraphs.filter((paragraph) => Boolean(paragraph.compareDocumentPosition(title) & Node.DOCUMENT_POSITION_FOLLOWING));
    const afterTitle = paragraphs.filter((paragraph) => Boolean(title.compareDocumentPosition(paragraph) & Node.DOCUMENT_POSITION_FOLLOWING));

    const kicker = beforeTitle.at(-1);
    if (kicker instanceof HTMLElement) kicker.classList.add('page-header-kicker');

    const description = afterTitle.find((paragraph) => !paragraph.closest('nav'));
    if (description instanceof HTMLElement) description.classList.add('page-header-description');

    const heroChild = Array.from(section.children).find((child) => child === hero || child.contains(hero));
    if (heroChild) {
        const summary = Array.from(section.children)
            .slice(Array.from(section.children).indexOf(heroChild) + 1)
            .find((child) => child.matches('.grid,[class*="grid-cols-"]') || child.querySelector(':scope > .grid,:scope > [class*="grid-cols-"]'));

        if (summary instanceof HTMLElement) summary.classList.add('page-header-summary');
    }
};

const normalizePageHeaders = (root = document) => {
    const appMain = document.querySelector('main');
    if (!appMain) return;

    const candidates = [];
    if (root instanceof HTMLElement && root.matches('h1')) candidates.push(root);
    if (root.querySelectorAll) candidates.push(...root.querySelectorAll('h1'));

    const firstPageTitle = candidates.find((title) => appMain.contains(title) && !title.closest('[role="dialog"]'));
    if (firstPageTitle) normalizeLegacyPageHeader(firstPageTitle);
};

normalizePageHeaders();
new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) normalizePageHeaders(node);
        });
    });
}).observe(document.body, { childList: true, subtree: true });
document.addEventListener('livewire:navigated', () => normalizePageHeaders());

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

const removeDeprecatedTransactionSignatoryFields = (root = document) => {
    ['signatory_name', 'signatory_role'].forEach((name) => {
        const field = root.querySelector?.(`[name="${name}"]`) || document.querySelector(`[name="${name}"]`);
        const wrapper = field?.closest('div');
        if (wrapper instanceof HTMLElement) wrapper.remove();
    });
};

removeDeprecatedTransactionSignatoryFields();
document.addEventListener('livewire:navigated', () => removeDeprecatedTransactionSignatoryFields());

const initializeSiplahPurchaseUi = (root = document) => {
    const paymentMethod = root.querySelector?.('select[name="payment_method"]') || document.querySelector('select[name="payment_method"]');
    if (!(paymentMethod instanceof HTMLSelectElement)) return;

    const form = paymentMethod.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    const internalFieldNames = ['order_number', 'order_date', 'bap_number', 'bap_date', 'bast_number', 'bast_date'];
    const internalWrappers = internalFieldNames
        .map((name) => form.querySelector(`[name="${name}"]`)?.closest('div'))
        .filter((element) => element instanceof HTMLElement);

    const orderField = form.querySelector('[name="order_number"]');
    const purchaseBlock = orderField?.closest('.rounded-lg');
    const purchaseHeading = purchaseBlock?.querySelector('p');
    const siplahOrderField = form.querySelector('[name="siplah_order_number"]');
    const siplahBlock = siplahOrderField?.closest('fieldset');
    const siplahHeading = siplahBlock?.querySelector('p');
    const siplahDescription = siplahHeading?.nextElementSibling;

    if (purchaseHeading instanceof HTMLElement && !purchaseHeading.dataset.defaultText) {
        purchaseHeading.dataset.defaultText = purchaseHeading.textContent?.trim() || 'Data pembelian barang/konsumsi';
    }

    let guidance = purchaseBlock?.querySelector('[data-siplah-marketplace-guidance]');
    if (!guidance && purchaseHeading instanceof HTMLElement) {
        guidance = document.createElement('div');
        guidance.dataset.siplahMarketplaceGuidance = 'true';
        guidance.className = 'mt-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs leading-relaxed text-emerald-900';
        guidance.innerHTML = '<span class="font-bold">Dokumen marketplace menjadi sumber pengadaan.</span> Surat Pesanan, BAP, dan BAST internal tidak diwajibkan untuk transaksi SiPLah. Invoice serta bukti penerimaan barang tetap harus dapat ditelusuri.';
        purchaseHeading.insertAdjacentElement('afterend', guidance);
    }

    const render = () => {
        const isSiplah = paymentMethod.value.toLowerCase() === 'siplah';

        internalWrappers.forEach((wrapper) => {
            wrapper.hidden = isSiplah;
        });

        if (purchaseHeading instanceof HTMLElement) {
            purchaseHeading.textContent = isSiplah
                ? 'Invoice & penerimaan pembelian SiPLah'
                : purchaseHeading.dataset.defaultText;
        }

        if (guidance instanceof HTMLElement) {
            guidance.hidden = !isSiplah;
        }

        if (siplahHeading instanceof HTMLElement) {
            siplahHeading.textContent = 'Data Pembelian SiPLah';
        }

        if (siplahDescription instanceof HTMLElement) {
            siplahDescription.textContent = isSiplah
                ? 'Data penyedia dan Nomor Pesanan SiPLah menjadi referensi transaksi marketplace. Dokumen pemesanan dan invoice asli tetap dipertahankan sebagai bukti sumber.'
                : siplahDescription.textContent;
        }
    };

    if (paymentMethod.dataset.siplahUiInitialized !== 'true') {
        paymentMethod.dataset.siplahUiInitialized = 'true';
        paymentMethod.addEventListener('change', render);
    }

    render();
};

initializeSiplahPurchaseUi();
document.addEventListener('livewire:navigated', () => initializeSiplahPurchaseUi());

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
