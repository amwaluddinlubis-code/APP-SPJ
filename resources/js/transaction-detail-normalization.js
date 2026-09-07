const detailObservers = new WeakMap();

const iconPaths = {
    'arrow-left': '<path d="M15 18l-6-6 6-6"/><path d="M9 12h12"/>',
    document: '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/><path d="M9 12h6"/><path d="M9 16h6"/>',
    edit: '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M12.5 7.5l4 4"/>',
    save: '<path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3"/><path d="M8 21v-7h8v7"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
    trash: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 15H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
    budget: '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M16 10h5v5h-5a2.5 2.5 0 0 1 0-5z"/><path d="M6 6V4h11"/>',
    tax: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h3"/><path d="M15 12h1"/><path d="M8 16h1"/><path d="M13 16h3"/>',
    balance: '<circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/>',
    items: '<path d="M4 5h16v5H4z"/><path d="M4 14h16v5H4z"/><path d="M8 7.5h.01"/><path d="M8 16.5h.01"/>',
    database: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
    checklist: '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6l1 1 2-2"/><path d="M4 12l1 1 2-2"/><path d="M4 18l1 1 2-2"/>',
    lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
};

const createIcon = (name, className = '') => {
    const span = document.createElement('span');
    span.className = `transaction-detail-inline-icon ${className}`.trim();
    span.setAttribute('aria-hidden', 'true');
    span.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${iconPaths[name] || iconPaths.document}</svg>`;

    return span;
};

const cleanActionArrows = (element) => {
    element.childNodes.forEach((node) => {
        if (node.nodeType !== Node.TEXT_NODE) return;
        node.nodeValue = node.nodeValue
            .replace(/^\s*[←+]+\s*/, '')
            .replace(/\s*→\s*$/, '');
    });
};

const prependIcon = (element, name) => {
    if (!(element instanceof HTMLElement) || element.dataset.transactionDetailIcon === 'true') return;

    cleanActionArrows(element);
    element.dataset.transactionDetailIcon = 'true';
    element.classList.add('transaction-detail-icon-action');
    element.prepend(createIcon(name));
};

const normalizeActionIcons = (page) => {
    const rules = [
        ['kembali ke transaksi', 'arrow-left'],
        ['buka paket spj', 'document'],
        ['buat spj', 'document'],
        ['isi data', 'edit'],
        ['simpan perbaikan paket', 'save'],
        ['buat paket spj', 'save'],
        ['simpan uraian barang/jasa', 'save'],
        ['ambil semua pegawai terdaftar', 'users'],
        ['ambil pegawai', 'users'],
        ['pekerja', 'plus'],
        ['peserta', 'plus'],
        ['pelaksana', 'plus'],
        ['penerima', 'plus'],
        ['hapus', 'trash'],
    ];

    page.querySelectorAll('a, button').forEach((element) => {
        if (element.dataset.transactionDetailIcon === 'true') return;
        const text = element.textContent?.replace(/\s+/g, ' ').trim().toLowerCase() || '';
        if (!text || ['↑', '↓', '⋮⋮'].includes(text)) return;

        const exactRule = rules.find(([needle]) => text === needle || text === `+ ${needle}`);
        const broadRule = exactRule || rules.find(([needle]) =>
            ['kembali ke transaksi', 'buka paket spj', 'buat spj', 'isi data', 'simpan perbaikan paket', 'buat paket spj', 'simpan uraian barang/jasa', 'ambil semua pegawai terdaftar', 'ambil pegawai'].includes(needle)
            && text.includes(needle));

        if (broadRule) prependIcon(element, broadRule[1]);
    });
};

const normalizeHeadingIcons = (page) => {
    const rules = [
        ['informasi referensi arkas / bku', 'database'],
        ['checklist kelengkapan', 'checklist'],
        ['rincian barang dan jasa', 'items'],
        ['rincian pajak', 'tax'],
        ['informasi dokumen spj', 'document'],
        ['modul pembuatan spj', 'document'],
    ];

    page.querySelectorAll('h2, p').forEach((element) => {
        if (element.dataset.transactionDetailHeadingIcon === 'true') return;
        const text = element.textContent?.replace(/\s+/g, ' ').trim().toLowerCase() || '';
        const rule = rules.find(([needle]) => text === needle);
        if (!rule) return;

        element.dataset.transactionDetailHeadingIcon = 'true';
        element.classList.add('transaction-detail-icon-heading');
        element.prepend(createIcon(rule[1]));
    });
};

const normalizeSummaryIcons = (page) => {
    const header = page.querySelector('.page-header-shell');
    if (!(header instanceof HTMLElement)) return;

    const rules = [
        ['nilai bruto', 'budget', 'indigo'],
        ['total pajak', 'tax', 'amber'],
        ['nilai dibayarkan', 'balance', 'emerald'],
        ['rincian barang/jasa', 'items', 'sky'],
    ];

    header.querySelectorAll('.ui-stat').forEach((stat) => {
        if (!(stat instanceof HTMLElement) || stat.dataset.transactionDetailSummary === 'true') return;
        const label = stat.querySelector('.ui-stat-label');
        if (!(label instanceof HTMLElement)) return;

        const text = label.textContent?.replace(/\s+/g, ' ').trim().toLowerCase() || '';
        const rule = rules.find(([needle]) => text === needle);
        if (!rule) return;

        let heading = stat.querySelector('.ui-stat-heading');
        if (!(heading instanceof HTMLElement)) {
            heading = document.createElement('div');
            heading.className = 'ui-stat-heading flex items-center gap-2.5';
            label.before(heading);
            heading.append(label);
        }

        heading.prepend(createIcon(rule[1], 'transaction-detail-stat-icon'));
        stat.dataset.transactionDetailSummary = 'true';
        stat.dataset.detailTone = rule[2];
    });
};

const normalizeArkasReference = (form) => {
    const field = form.querySelector('textarea[name="description"][readonly], input[name="description"][readonly]');
    if (!(field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement)) return;

    const wrapper = field.closest('div');
    if (!(wrapper instanceof HTMLElement) || wrapper.dataset.transactionArkasReference === 'true') return;

    const value = field.value?.trim() || '';
    const label = wrapper.querySelector('label');
    if (label instanceof HTMLElement) {
        label.textContent = '';
        label.classList.add('transaction-detail-reference-label');
        label.append(createIcon('database'));
        const text = document.createElement('span');
        text.textContent = 'Uraian Transaksi ARKAS';
        label.append(text);
    }

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = field.name;
    hidden.value = field.value || '';

    const info = document.createElement('div');
    info.className = 'transaction-detail-reference-text';
    info.textContent = value || 'Uraian transaksi dari ARKAS belum tersedia.';

    field.replaceWith(hidden, info);
    wrapper.dataset.transactionArkasReference = 'true';
    wrapper.classList.add('transaction-detail-reference-block');
};

const automaticDefinitions = [
    { name: 'order_number', label: 'Nomor Pesanan', categories: ['BARANG', 'KONSUMSI'], tone: 'indigo' },
    { name: 'bap_number', label: 'Nomor BAP', categories: ['BARANG', 'KONSUMSI'], tone: 'amber' },
    { name: 'bast_number', label: 'Nomor BAST', categories: ['BARANG', 'KONSUMSI'], tone: 'emerald' },
    { name: 'spk_number', label: 'Nomor SPK', categories: ['PEMELIHARAAN'], tone: 'sky' },
    { name: 'rab_number', label: 'Nomor RAB', categories: ['PEMELIHARAAN'], tone: 'violet' },
];

const normalizeStaticAutomaticFields = (form) => {
    automaticDefinitions.forEach(({ name }) => {
        form.querySelectorAll(`input[name="${name}"]`).forEach((field) => {
            if (!(field instanceof HTMLInputElement)) return;
            const wrapper = field.closest('div');
            if (!(wrapper instanceof HTMLElement)) return;

            wrapper.classList.add('transaction-detail-auto-source-field');
            field.type = 'hidden';
            field.removeAttribute('readonly');
            field.dataset.transactionAutomaticNumber = 'true';
        });
    });
};

const normalizeTravelAutomaticFields = (form) => {
    form.querySelectorAll('input[readonly]').forEach((field) => {
        if (!(field instanceof HTMLInputElement)) return;
        const name = field.getAttribute('name') || '';
        if (!name.endsWith('[assignment_letter_number]')) return;

        const wrapper = field.closest('label') || field.closest('div');
        if (!(wrapper instanceof HTMLElement) || wrapper.dataset.transactionAutomaticTravel === 'true') return;

        const label = wrapper.querySelector(':scope > span');
        if (label instanceof HTMLElement) label.hidden = true;

        const info = document.createElement('div');
        info.className = 'transaction-detail-inline-auto-number';

        const heading = document.createElement('span');
        heading.className = 'transaction-detail-inline-auto-number-label';
        heading.append(createIcon('document'));
        const headingText = document.createElement('span');
        headingText.textContent = 'Nomor Surat Tugas';
        heading.append(headingText);

        const value = document.createElement('strong');
        value.textContent = field.value?.trim() || 'Akan terbit otomatis setelah penomoran';
        if (!field.value?.trim()) value.classList.add('is-pending');

        info.append(heading, value);
        field.type = 'hidden';
        field.removeAttribute('readonly');
        field.dataset.transactionAutomaticNumber = 'true';
        wrapper.append(info);
        wrapper.dataset.transactionAutomaticTravel = 'true';
    });
};

const renderAutomaticNumberSummary = (form) => {
    const categoryField = form.querySelector('[name="spj_category"]');
    const paymentField = form.querySelector('[name="payment_method"]');
    const category = categoryField instanceof HTMLSelectElement ? categoryField.value.toUpperCase() : '';
    const paymentMethod = paymentField instanceof HTMLSelectElement ? paymentField.value.toLowerCase() : '';
    const referenceWrapper = form.querySelector('[data-transaction-arkas-reference="true"]');
    if (!(referenceWrapper instanceof HTMLElement)) return;

    let summary = referenceWrapper.querySelector('[data-transaction-auto-number-summary]');
    if (!(summary instanceof HTMLElement)) {
        summary = document.createElement('div');
        summary.dataset.transactionAutoNumberSummary = 'true';
        summary.className = 'transaction-detail-auto-number-summary';
        referenceWrapper.append(summary);
    }

    const definitions = automaticDefinitions.filter((definition) => {
        if (!definition.categories.includes(category)) return false;
        if (['BARANG', 'KONSUMSI'].includes(category) && paymentMethod === 'siplah') return false;
        return true;
    });

    summary.replaceChildren();
    summary.hidden = definitions.length === 0;
    if (!definitions.length) return;

    const title = document.createElement('div');
    title.className = 'transaction-detail-auto-number-title';
    title.append(createIcon('document'));
    const titleText = document.createElement('span');
    titleText.textContent = 'Nomor Dokumen Otomatis';
    title.append(titleText);
    summary.append(title);

    definitions.forEach((definition) => {
        const field = form.querySelector(`input[name="${definition.name}"]`);
        const row = document.createElement('div');
        row.className = 'transaction-detail-auto-number-row';
        row.dataset.tone = definition.tone;

        const label = document.createElement('span');
        label.textContent = definition.label;

        const value = document.createElement('strong');
        const currentValue = field instanceof HTMLInputElement ? field.value.trim() : '';
        value.textContent = currentValue || 'Akan terbit otomatis setelah penomoran';
        if (!currentValue) value.classList.add('is-pending');

        row.append(label, value);
        summary.append(row);
    });
};

const normalizeCopy = (page) => {
    const replacements = new Map([
        ['Isi invoice atau nomor pesanan.', 'Lengkapi invoice dan tanggal dokumen; nomor pesanan diterbitkan otomatis saat penomoran.'],
        ['Isi BAP atau BAST jika sudah tersedia.', 'Lengkapi tanggal BAP/BAST; nomor dokumen diterbitkan otomatis saat penomoran.'],
    ]);

    page.querySelectorAll('p, span').forEach((element) => {
        const text = element.textContent?.trim();
        if (!text || !replacements.has(text)) return;
        element.textContent = replacements.get(text);
    });

    page.querySelectorAll('p').forEach((element) => {
        if (!element.textContent?.includes('field tanpa') || !element.textContent.includes('terisi otomatis')) return;
        element.innerHTML = '<strong>Wajib diisi.</strong> Penanda menyesuaikan kategori SPJ yang dipilih; field tanpa tanda bintang bersifat opsional. Nomor dokumen otomatis ditampilkan sebagai informasi dan tidak perlu diisi operator.';
    });
};

const normalizeLockIcon = (page) => {
    page.querySelectorAll('span[aria-hidden="true"]').forEach((span) => {
        if (span.textContent?.trim() !== '🔒') return;
        span.replaceWith(createIcon('lock'));
    });
};

const bindDetailControls = (form) => {
    if (form.dataset.transactionDetailControlsBound === 'true') return;
    form.dataset.transactionDetailControlsBound = 'true';

    ['spj_category', 'payment_method'].forEach((name) => {
        const field = form.querySelector(`[name="${name}"]`);
        field?.addEventListener('change', () => requestAnimationFrame(() => {
            normalizeStaticAutomaticFields(form);
            normalizeTravelAutomaticFields(form);
            renderAutomaticNumberSummary(form);
        }));
    });
};

const refreshDetailForm = (form, page) => {
    normalizeArkasReference(form);
    normalizeStaticAutomaticFields(form);
    normalizeTravelAutomaticFields(form);
    renderAutomaticNumberSummary(form);
    normalizeSummaryIcons(page);
    normalizeActionIcons(page);
    normalizeHeadingIcons(page);
    normalizeLockIcon(page);
    normalizeCopy(page);
    bindDetailControls(form);
};

export const initializeTransactionDetailUi = (root = document) => {
    const selector = 'form[action*="/spj/"][action*="/siapkan"]';
    const form = root instanceof HTMLFormElement && root.matches(selector)
        ? root
        : root.querySelector?.(selector) || document.querySelector(selector);
    if (!(form instanceof HTMLFormElement)) return;

    const main = form.closest('main') || document.querySelector('main');
    if (!(main instanceof HTMLElement)) return;
    main.classList.add('transaction-detail-page');

    const page = form.closest('.flex.flex-col.gap-6') || main;
    refreshDetailForm(form, page);

    if (!detailObservers.has(form)) {
        let scheduled = false;
        const observer = new MutationObserver((mutations) => {
            const hasAddedElements = mutations.some((mutation) =>
                Array.from(mutation.addedNodes).some((node) => node.nodeType === Node.ELEMENT_NODE));
            if (!hasAddedElements || scheduled) return;

            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                refreshDetailForm(form, page);
            });
        });
        observer.observe(form, { childList: true, subtree: true });
        detailObservers.set(form, observer);
    }
};

const bootTransactionDetailUi = () => requestAnimationFrame(() => initializeTransactionDetailUi(document));

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTransactionDetailUi, { once: true });
} else {
    bootTransactionDetailUi();
}

document.addEventListener('livewire:navigated', bootTransactionDetailUi);
