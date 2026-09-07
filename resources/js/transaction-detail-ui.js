const iconPaths = {
    back: '<path d="M15 18l-6-6 6-6"/><path d="M9 12h12"/>',
    document: '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/><path d="M9 12h6"/><path d="M9 16h6"/>',
    edit: '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M12.5 7.5l4 4"/>',
    save: '<path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3"/><path d="M8 21v-7h8v7"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
    plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
    trash: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 15H6L5 6"/>',
    budget: '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M16 10h5v5h-5a2.5 2.5 0 0 1 0-5z"/>',
    tax: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h3"/><path d="M13 16h3"/>',
    balance: '<circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/>',
    items: '<path d="M4 5h16v5H4z"/><path d="M4 14h16v5H4z"/>',
    database: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
    checklist: '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6l1 1 2-2"/>',
    lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
};

const makeIcon = (name, extraClass = '') => {
    const span = document.createElement('span');
    span.className = `transaction-detail-inline-icon ${extraClass}`.trim();
    span.setAttribute('aria-hidden', 'true');
    span.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${iconPaths[name] || iconPaths.document}</svg>`;
    return span;
};

const normalizeButtonText = (element) => {
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
        if (walker.currentNode.nodeValue?.trim()) nodes.push(walker.currentNode);
    }
    if (!nodes.length) return;

    nodes[0].nodeValue = nodes[0].nodeValue.replace(/^\s*[←+]+\s*/, '');
    nodes[nodes.length - 1].nodeValue = nodes[nodes.length - 1].nodeValue.replace(/\s*→\s*$/, '');
};

const addIcon = (element, icon) => {
    if (!(element instanceof HTMLElement)) return;
    normalizeButtonText(element);
    if (element.dataset.detailIcon === '1') return;
    element.dataset.detailIcon = '1';
    element.classList.add('transaction-detail-icon-action');
    element.prepend(makeIcon(icon));
};

const enhanceActions = (page) => {
    const rules = [
        ['kembali ke transaksi', 'back'], ['buka paket spj', 'document'], ['buat spj', 'document'],
        ['isi data', 'edit'], ['simpan perbaikan paket', 'save'], ['buat paket spj', 'save'],
        ['simpan uraian barang/jasa', 'save'], ['ambil semua pegawai terdaftar', 'users'],
        ['ambil pegawai', 'users'], ['pekerja', 'plus'], ['peserta manual', 'plus'],
        ['pelaksana', 'plus'], ['manual', 'plus'], ['penerima', 'plus'], ['hapus', 'trash'],
    ];

    page.querySelectorAll('a, button').forEach((element) => {
        const raw = element.textContent?.replace(/\s+/g, ' ').trim().toLowerCase() || '';
        if (!raw || ['↑', '↓', '⋮⋮'].includes(raw)) return;

        if (element.dataset.detailIcon === '1') {
            normalizeButtonText(element);
            return;
        }

        const hasLeadingPlus = /^\+\s*/.test(raw);
        const text = raw.replace(/^\+\s*/, '').replace(/^←\s*/, '').replace(/\s*→$/, '');
        const rule = rules.find(([needle]) => text === needle || (needle.length > 7 && text.includes(needle)));
        if (rule) addIcon(element, rule[1]);
        else if (hasLeadingPlus) addIcon(element, 'plus');
    });
};

const enhanceHeadings = (page) => {
    const rules = new Map([
        ['informasi referensi arkas / bku', 'database'], ['checklist kelengkapan', 'checklist'],
        ['rincian barang dan jasa', 'items'], ['rincian pajak', 'tax'],
        ['informasi dokumen spj', 'document'], ['modul pembuatan spj', 'document'],
    ]);
    page.querySelectorAll('h2, p').forEach((element) => {
        if (element.dataset.detailHeadingIcon === '1') return;
        const icon = rules.get(element.textContent?.replace(/\s+/g, ' ').trim().toLowerCase() || '');
        if (!icon) return;
        element.dataset.detailHeadingIcon = '1';
        element.classList.add('transaction-detail-icon-heading');
        element.prepend(makeIcon(icon));
    });
};

const enhanceSummary = (page) => {
    const header = page.querySelector('.page-header-shell');
    if (!(header instanceof HTMLElement)) return;
    const rules = new Map([
        ['nilai bruto', ['budget', 'indigo']], ['total pajak', ['tax', 'amber']],
        ['nilai dibayarkan', ['balance', 'emerald']], ['rincian barang/jasa', ['items', 'sky']],
    ]);
    header.querySelectorAll('.ui-stat').forEach((stat) => {
        if (!(stat instanceof HTMLElement) || stat.dataset.detailSummary === '1') return;
        const label = stat.querySelector('.ui-stat-label');
        if (!(label instanceof HTMLElement)) return;
        const rule = rules.get(label.textContent?.replace(/\s+/g, ' ').trim().toLowerCase() || '');
        if (!rule) return;
        const heading = document.createElement('div');
        heading.className = 'ui-stat-heading flex items-center gap-2.5';
        label.before(heading);
        heading.append(makeIcon(rule[0], 'transaction-detail-stat-icon'), label);
        stat.dataset.detailSummary = '1';
        stat.dataset.detailTone = rule[1];
    });
};

const normalizeArkasReference = (form) => {
    const field = form.querySelector('textarea[name="description"][readonly], input[name="description"][readonly]');
    if (!(field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement)) return;
    const wrapper = field.closest('div');
    if (!(wrapper instanceof HTMLElement)) return;

    const label = wrapper.querySelector('label');
    if (label instanceof HTMLElement) {
        label.replaceChildren(makeIcon('database'));
        const text = document.createElement('span');
        text.textContent = 'Uraian Transaksi ARKAS';
        label.append(text);
        label.classList.add('transaction-detail-reference-label');
    }
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'description';
    hidden.value = field.value || '';
    const info = document.createElement('div');
    info.className = 'transaction-detail-reference-text';
    info.textContent = field.value?.trim() || 'Uraian transaksi dari ARKAS belum tersedia.';
    field.replaceWith(hidden, info);
    wrapper.dataset.transactionArkasReference = 'true';
    wrapper.classList.add('transaction-detail-reference-block');
};

const automaticDefinitions = [
    ['order_number', 'Nomor Pesanan', ['BARANG', 'KONSUMSI'], 'indigo'],
    ['bap_number', 'Nomor BAP', ['BARANG', 'KONSUMSI'], 'amber'],
    ['bast_number', 'Nomor BAST', ['BARANG', 'KONSUMSI'], 'emerald'],
    ['spk_number', 'Nomor SPK', ['PEMELIHARAAN'], 'sky'],
    ['rab_number', 'Nomor RAB', ['PEMELIHARAAN'], 'violet'],
];

const hideAutomaticInputs = (form) => {
    automaticDefinitions.forEach(([name]) => {
        form.querySelectorAll(`input[name="${name}"]`).forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            const wrapper = input.closest('div');
            if (wrapper instanceof HTMLElement) wrapper.classList.add('transaction-detail-auto-source-field');
            input.type = 'hidden';
            input.removeAttribute('readonly');
        });
    });
};

const normalizeTravelNumbers = (form) => {
    form.querySelectorAll('input[readonly]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        if (!(input.name || '').endsWith('[assignment_letter_number]')) return;
        const wrapper = input.closest('label') || input.closest('div');
        if (!(wrapper instanceof HTMLElement) || wrapper.dataset.autoTravelInfo === '1') return;
        const oldLabel = wrapper.querySelector(':scope > span');
        if (oldLabel instanceof HTMLElement) oldLabel.hidden = true;
        const info = document.createElement('div');
        info.className = 'transaction-detail-inline-auto-number';
        const heading = document.createElement('span');
        heading.className = 'transaction-detail-inline-auto-number-label';
        heading.append(makeIcon('document'));
        const title = document.createElement('span');
        title.textContent = 'Nomor Surat Tugas';
        heading.append(title);
        const value = document.createElement('strong');
        value.textContent = input.value?.trim() || 'Akan terbit otomatis setelah tanggal dokumen dilengkapi dan penomoran dilakukan';
        if (!input.value?.trim()) value.classList.add('is-pending');
        info.append(heading, value);
        input.type = 'hidden';
        input.removeAttribute('readonly');
        wrapper.append(info);
        wrapper.dataset.autoTravelInfo = '1';
    });
};

const renderAutomaticSummary = (form) => {
    const category = form.querySelector('[name="spj_category"]')?.value?.toUpperCase() || '';
    const method = form.querySelector('[name="payment_method"]')?.value?.toLowerCase() || '';
    const reference = form.querySelector('[data-transaction-arkas-reference="true"]');
    if (!(reference instanceof HTMLElement)) return;
    const definitions = automaticDefinitions.filter(([, , categories]) => categories.includes(category) && !(method === 'siplah' && ['BARANG', 'KONSUMSI'].includes(category)));
    const signature = definitions.map(([name]) => `${name}:${form.querySelector(`input[name="${name}"]`)?.value || ''}`).join('|');
    let summary = reference.querySelector('[data-transaction-auto-number-summary]');
    if (!(summary instanceof HTMLElement)) {
        summary = document.createElement('div');
        summary.dataset.transactionAutoNumberSummary = 'true';
        summary.className = 'transaction-detail-auto-number-summary';
        reference.append(summary);
    }
    summary.hidden = definitions.length === 0;
    if (!definitions.length) return;
    if (summary.dataset.signature === signature) return;
    summary.dataset.signature = signature;
    summary.replaceChildren();
    const title = document.createElement('div');
    title.className = 'transaction-detail-auto-number-title';
    title.append(makeIcon('document'));
    const titleText = document.createElement('span');
    titleText.textContent = 'Nomor Dokumen Otomatis';
    title.append(titleText);
    summary.append(title);
    definitions.forEach(([name, label, , tone]) => {
        const row = document.createElement('div');
        row.className = 'transaction-detail-auto-number-row';
        row.dataset.tone = tone;
        const rowLabel = document.createElement('span');
        rowLabel.textContent = label;
        const value = document.createElement('strong');
        const current = form.querySelector(`input[name="${name}"]`)?.value?.trim() || '';
        value.textContent = current || 'Akan terbit otomatis setelah tanggal dokumen dilengkapi dan penomoran dilakukan';
        if (!current) value.classList.add('is-pending');
        row.append(rowLabel, value);
        summary.append(row);
    });
};

const makePanel = (title, icon, className) => {
    const panel = document.createElement('section');
    panel.className = `transaction-detail-form-panel ${className}`;

    const heading = document.createElement('div');
    heading.className = 'transaction-detail-form-panel-heading';
    heading.append(makeIcon(icon));
    const label = document.createElement('h3');
    label.textContent = title;
    heading.append(label);

    const content = document.createElement('div');
    content.className = 'transaction-detail-form-panel-content';
    panel.append(heading, content);

    return { panel, content };
};

const categoryFieldset = (form, category) => Array.from(form.querySelectorAll('fieldset')).find((fieldset) =>
    (fieldset.getAttribute('x-show') || '').includes(`category === '${category}'`));

const ensureFormLayout = (form) => {
    const rootFieldset = Array.from(form.children).find((child) => child instanceof HTMLFieldSetElement);
    if (!(rootFieldset instanceof HTMLFieldSetElement)) return;

    let layout = rootFieldset.querySelector(':scope > .transaction-detail-form-layout');
    let fullWidth = rootFieldset.querySelector(':scope > .transaction-detail-fullwidth-sections');

    if (!(layout instanceof HTMLElement)) {
        const originalChildren = Array.from(rootFieldset.children);
        const requiredNotice = originalChildren.find((child) => child.textContent?.includes('Wajib diisi.'));
        const submitButton = Array.from(rootFieldset.querySelectorAll('button')).find((button) => {
            const text = button.textContent?.replace(/\s+/g, ' ').trim().toLowerCase() || '';
            return text.includes('buat paket spj') || text.includes('simpan perbaikan paket') || text.includes('paket terkunci');
        });
        const submitRow = submitButton?.parentElement;
        const submitIndex = submitRow ? originalChildren.indexOf(submitRow) : originalChildren.length;

        layout = document.createElement('div');
        layout.className = 'transaction-detail-form-layout';
        layout.dataset.transactionDetailLayout = 'true';

        const infoPanel = makePanel('Informasi dan Penomoran Otomatis', 'database', 'transaction-detail-info-panel');
        const operatorPanel = makePanel('Form Input Oleh Operator', 'edit', 'transaction-detail-operator-panel');
        layout.append(infoPanel.panel, operatorPanel.panel);

        fullWidth = document.createElement('div');
        fullWidth.className = 'transaction-detail-fullwidth-sections';
        fullWidth.dataset.transactionDetailFullwidth = 'true';

        if (requiredNotice instanceof HTMLElement) requiredNotice.insertAdjacentElement('afterend', layout);
        else rootFieldset.prepend(layout);
        layout.insertAdjacentElement('afterend', fullWidth);

        const specialFieldsets = new Set([
            categoryFieldset(form, 'PEMELIHARAAN'),
            categoryFieldset(form, 'HONOR_PEGAWAI'),
            categoryFieldset(form, 'JASA_LAINNYA'),
        ].filter(Boolean));

        originalChildren.slice(0, submitIndex).forEach((child) => {
            if (!(child instanceof HTMLElement) || child === requiredNotice) return;
            if (specialFieldsets.has(child)) fullWidth.append(child);
            else operatorPanel.content.append(child);
        });

        const reference = form.querySelector('[data-transaction-arkas-reference="true"]');
        if (reference instanceof HTMLElement) infoPanel.content.append(reference);
    }

    const reference = form.querySelector('[data-transaction-arkas-reference="true"]');
    const infoContent = layout.querySelector('.transaction-detail-info-panel .transaction-detail-form-panel-content');
    if (reference instanceof HTMLElement && infoContent instanceof HTMLElement && reference.parentElement !== infoContent) {
        infoContent.append(reference);
    }

    const category = form.querySelector('[name="spj_category"]')?.value?.toUpperCase() || '';
    if (fullWidth instanceof HTMLElement) {
        fullWidth.hidden = !['PEMELIHARAAN', 'HONOR_PEGAWAI', 'JASA_LAINNYA'].includes(category);
    }
};

const normalizeCopy = (page) => {
    const replacements = new Map([
        ['Isi invoice atau nomor pesanan.', 'Lengkapi invoice dan tanggal dokumen; nomor pesanan diterbitkan otomatis saat penomoran.'],
        ['Isi BAP atau BAST jika sudah tersedia.', 'Lengkapi tanggal BAP/BAST; nomor dokumen diterbitkan otomatis saat penomoran.'],
    ]);
    page.querySelectorAll('p, span').forEach((element) => {
        const replacement = replacements.get(element.textContent?.trim());
        if (replacement) element.textContent = replacement;
    });
    page.querySelectorAll('p').forEach((element) => {
        if (!element.textContent?.includes('field tanpa') || !element.textContent.includes('terisi otomatis')) return;
        element.textContent = 'Wajib diisi. Penanda menyesuaikan kategori SPJ yang dipilih; field tanpa tanda bintang bersifat opsional. Nomor dokumen otomatis ditampilkan sebagai informasi dan tidak perlu diisi operator.';
    });
};

const replaceLockEmoji = (page) => {
    page.querySelectorAll('span[aria-hidden="true"]').forEach((span) => {
        if (span.textContent?.trim() === '🔒') span.replaceWith(makeIcon('lock'));
    });
};

const refresh = (form, page) => {
    normalizeArkasReference(form);
    ensureFormLayout(form);
    hideAutomaticInputs(form);
    normalizeTravelNumbers(form);
    renderAutomaticSummary(form);
    enhanceSummary(page);
    enhanceActions(page);
    enhanceHeadings(page);
    replaceLockEmoji(page);
    normalizeCopy(page);
};

const initialize = (root = document) => {
    const form = root.querySelector?.('form[action*="/spj/"][action*="/siapkan"]') || document.querySelector('form[action*="/spj/"][action*="/siapkan"]');
    if (!(form instanceof HTMLFormElement)) return;
    const main = form.closest('main') || document.querySelector('main');
    if (!(main instanceof HTMLElement)) return;
    main.classList.add('transaction-detail-page');
    const page = form.closest('.flex.flex-col.gap-6') || main;
    refresh(form, page);
    if (form.dataset.detailUiBound === '1') return;
    form.dataset.detailUiBound = '1';
    ['spj_category', 'payment_method'].forEach((name) => {
        form.querySelector(`[name="${name}"]`)?.addEventListener('change', () => requestAnimationFrame(() => refresh(form, page)));
    });
    const observer = new MutationObserver((mutations) => {
        const needsRefresh = mutations.some((mutation) => Array.from(mutation.addedNodes).some((node) => {
            if (!(node instanceof Element)) return false;
            if (node.matches('.transaction-detail-inline-icon, .transaction-detail-auto-number-row, .transaction-detail-auto-number-title, .transaction-detail-inline-auto-number')) return false;
            if (node.closest('[data-transaction-auto-number-summary]')) return false;
            return node.matches('input[readonly], a, button') || Boolean(node.querySelector('input[readonly], a, button'));
        }));
        if (needsRefresh) requestAnimationFrame(() => refresh(form, page));
    });
    observer.observe(form, { childList: true, subtree: true });
};

const boot = () => requestAnimationFrame(() => initialize(document));
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
else boot();
document.addEventListener('livewire:navigated', boot);