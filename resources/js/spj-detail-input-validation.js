const FORM_SELECTOR = '#spj-manual-form';

const INTEGER_FIELD_PATTERNS = [
    /^participant_count$/,
    /^participants\[\d+\]\[portions\]$/,
    /^workers\[\d+\]\[work_days\]$/,
    /^service_recipients\[\d+\]\[(quantity|rental_days)\]$/,
];

const MONEY_FIELD_PATTERNS = [
    /^workers\[\d+\]\[daily_rate\]$/,
    /^travels\[\d+\]\[amount\]$/,
    /^service_recipients\[\d+\]\[daily_rate\]$/,
];

const DIRECT_ACCOUNTING_FIELD_PATTERNS = [
    /^service_recipients\[\d+\]\[daily_rate\]$/,
];

const TOTAL_CONFIG = {
    HONOR_PEGAWAI: {
        prefix: 'workers',
        nameField: 'name',
        amount: (row) => numberValue(row.work_days) * moneyValue(row.daily_rate),
    },
    PEMELIHARAAN: {
        prefix: 'workers',
        nameField: 'name',
        amount: (row) => numberValue(row.work_days) * moneyValue(row.daily_rate),
    },
    SPPD: {
        prefix: 'travels',
        nameField: 'traveler_name',
        amount: (row) => moneyValue(row.amount),
    },
    JASA_LAINNYA: {
        prefix: 'service_recipients',
        nameField: 'name',
        amount: (row) => numberValue(row.quantity) * numberValue(row.rental_days) * moneyValue(row.daily_rate),
    },
};

const matchesAny = (name, patterns) => patterns.some((pattern) => pattern.test(name || ''));

const numberValue = (value) => {
    const parsed = Number(String(value ?? '').replace(',', '.'));
    return Number.isFinite(parsed) ? parsed : 0;
};

const parseAccounting = (value) => {
    const digits = String(value ?? '').replace(/[^0-9]/g, '');
    return digits === '' ? 0 : Number(digits);
};

const moneyValue = (value) => {
    const text = String(value ?? '').trim();
    if (text === '') return 0;
    if (/^[0-9]+(?:\.[0-9]+)?$/.test(text)) return Math.round(Number(text));
    return parseAccounting(text);
};

const formatAccounting = (value) => new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
}).format(Math.round(Number(value) || 0));

const formatRupiah = (value) => `Rp ${formatAccounting(value)}`;

const normalizeInteger = (value) => {
    const raw = String(value ?? '').trim();
    if (raw === '') return '';
    const decimalIndex = raw.search(/[.,]/);
    const wholePart = decimalIndex >= 0 ? raw.slice(0, decimalIndex) : raw;
    return wholePart.replace(/[^0-9]/g, '');
};

const setupIntegerInput = (input) => {
    if (!(input instanceof HTMLInputElement) || !matchesAny(input.name, INTEGER_FIELD_PATTERNS)) return;
    if (input.dataset.spjIntegerInitialized === 'true') return;

    input.dataset.spjIntegerInitialized = 'true';
    input.dataset.spjIntegerMin = input.getAttribute('min') ?? '';
    input.type = 'text';
    input.inputMode = 'numeric';
    input.pattern = '[0-9]*';
    input.removeAttribute('step');

    const normalized = normalizeInteger(input.value);
    if (input.value !== normalized) input.value = normalized;
};

const validateIntegerMinimum = (input) => {
    if (!(input instanceof HTMLInputElement) || input.dataset.spjIntegerInitialized !== 'true') return;
    const value = normalizeInteger(input.value);
    const minText = input.dataset.spjIntegerMin;
    const min = minText === '' ? null : Number(minText);

    input.value = value;
    if (value !== '' && min !== null && Number(value) < min) {
        input.setCustomValidity(`Nilai minimal ${min}.`);
    } else {
        input.setCustomValidity('');
    }
};

const setupDirectAccountingInput = (input) => {
    if (!(input instanceof HTMLInputElement) || input.type === 'hidden') return;
    if (!matchesAny(input.name, DIRECT_ACCOUNTING_FIELD_PATTERNS)) return;
    if (input.dataset.spjAccountingInitialized === 'true') return;

    input.dataset.spjAccountingInitialized = 'true';
    input.type = 'text';
    input.inputMode = 'numeric';
    input.removeAttribute('step');
    input.value = formatAccounting(moneyValue(input.value));
};

const setupInputs = (root = document) => {
    root.querySelectorAll?.('input[name]').forEach((input) => {
        setupIntegerInput(input);
        setupDirectAccountingInput(input);
    });
};

const collectRows = (section, prefix) => {
    const rows = new Map();
    const expression = new RegExp(`^${prefix}\\[(\\d+)\\]\\[([^\\]]+)\\]$`);

    section.querySelectorAll('input[name], textarea[name], select[name]').forEach((field) => {
        const match = field.name?.match(expression);
        if (!match) return;
        const index = Number(match[1]);
        const key = match[2];
        if (!rows.has(index)) rows.set(index, {});
        rows.get(index)[key] = field.value;
    });

    return [...rows.entries()]
        .sort(([left], [right]) => left - right)
        .map(([, row]) => row);
};

const selectedCategory = (form) => String(form.querySelector('#spj-type, [name="spj_category"]')?.value || '').toUpperCase();

const activeSection = (form) => {
    const category = selectedCategory(form);
    if (!TOTAL_CONFIG[category]) return null;
    return form.querySelector(`fieldset[data-spj-section="${category}"]`);
};

const findGrossAmount = () => {
    const summary = document.querySelector('[data-spj-package-summary]');
    if (!summary) return 0;

    for (const container of summary.querySelectorAll('div')) {
        const paragraphs = [...container.children].filter((child) => child.tagName === 'P');
        if ((paragraphs[0]?.textContent || '').trim().toUpperCase() !== 'BRUTO') continue;
        return parseAccounting(paragraphs[1]?.textContent || '0');
    }

    return 0;
};

const detailTotal = (section, category) => {
    const config = TOTAL_CONFIG[category];
    if (!config) return 0;

    return collectRows(section, config.prefix)
        .filter((row) => String(row[config.nameField] || '').trim() !== '')
        .reduce((sum, row) => sum + config.amount(row), 0);
};

const ensurePanel = (section) => {
    let panel = section.querySelector('[data-spj-live-total]');
    if (panel) return panel;

    panel = document.createElement('div');
    panel.dataset.spjLiveTotal = 'true';
    panel.className = 'mt-3 rounded-lg border px-3 py-3 text-xs';
    panel.setAttribute('aria-live', 'polite');
    panel.innerHTML = `
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-bold text-[var(--ui-fg-strong)]">Validasi total biaya</p>
                <p data-spj-total-message class="mt-1 font-semibold"></p>
            </div>
            <dl class="grid grid-cols-3 gap-x-4 gap-y-1 text-right font-mono">
                <div><dt class="font-sans text-[10px] uppercase tracking-wide text-[var(--ui-fg-muted)]">Total rincian</dt><dd data-spj-detail-total class="font-bold"></dd></div>
                <div><dt class="font-sans text-[10px] uppercase tracking-wide text-[var(--ui-fg-muted)]">Bruto transaksi</dt><dd data-spj-gross-total class="font-bold"></dd></div>
                <div><dt class="font-sans text-[10px] uppercase tracking-wide text-[var(--ui-fg-muted)]">Selisih</dt><dd data-spj-difference class="font-bold"></dd></div>
            </dl>
        </div>
    `;
    section.append(panel);

    return panel;
};

const renderSectionTotal = (form, section) => {
    const category = String(section.dataset.spjSection || '').toUpperCase();
    if (!TOTAL_CONFIG[category]) return null;

    const total = Math.round(detailTotal(section, category));
    const gross = Math.round(findGrossAmount());
    const difference = total - gross;
    const matches = difference === 0;
    const panel = ensurePanel(section);

    panel.dataset.spjTotalMatches = matches ? 'true' : 'false';
    panel.classList.toggle('border-emerald-200', matches);
    panel.classList.toggle('bg-emerald-50', matches);
    panel.classList.toggle('text-emerald-800', matches);
    panel.classList.toggle('border-rose-200', !matches);
    panel.classList.toggle('bg-rose-50', !matches);
    panel.classList.toggle('text-rose-800', !matches);

    panel.querySelector('[data-spj-detail-total]').textContent = formatRupiah(total);
    panel.querySelector('[data-spj-gross-total]').textContent = formatRupiah(gross);
    panel.querySelector('[data-spj-difference]').textContent = formatRupiah(Math.abs(difference));

    const message = panel.querySelector('[data-spj-total-message]');
    if (matches) {
        message.textContent = 'Total rincian sudah sama dengan bruto transaksi.';
    } else if (difference < 0) {
        message.textContent = `Total rincian masih kurang ${formatRupiah(Math.abs(difference))}. Lengkapi rincian sebelum menyimpan.`;
    } else {
        message.textContent = `Total rincian melebihi bruto transaksi sebesar ${formatRupiah(difference)}. Koreksi rincian sebelum menyimpan.`;
    }

    return { panel, total, gross, difference, matches };
};

const renderAllTotals = (form) => {
    form.querySelectorAll('fieldset[data-spj-section]').forEach((section) => {
        const category = String(section.dataset.spjSection || '').toUpperCase();
        if (TOTAL_CONFIG[category]) renderSectionTotal(form, section);
    });
};

let syncFrame = null;
const scheduleSync = (form) => {
    if (syncFrame !== null) cancelAnimationFrame(syncFrame);
    syncFrame = requestAnimationFrame(() => {
        syncFrame = null;
        setupInputs(form);
        renderAllTotals(form);
    });
};

const normalizeMoneyForSubmit = (form) => {
    form.querySelectorAll('input[name]').forEach((input) => {
        if (!matchesAny(input.name, MONEY_FIELD_PATTERNS)) return;
        const amount = input.type === 'hidden' ? Math.round(numberValue(input.value)) : moneyValue(input.value);
        input.value = String(amount);
    });
};

const restoreDirectAccountingDisplay = (form) => {
    requestAnimationFrame(() => {
        form.querySelectorAll('input[name]').forEach((input) => {
            if (input.dataset.spjAccountingInitialized === 'true') {
                input.value = formatAccounting(moneyValue(input.value));
            }
        });
    });
};

const initializeForm = (form) => {
    if (!(form instanceof HTMLFormElement) || form.dataset.spjDetailValidationInitialized === 'true') return;
    form.dataset.spjDetailValidationInitialized = 'true';

    setupInputs(form);
    renderAllTotals(form);

    form.addEventListener('submit', (event) => {
        normalizeMoneyForSubmit(form);
        const section = activeSection(form);
        if (!section || section.disabled || section.hidden) return;

        const result = renderSectionTotal(form, section);
        if (!result || result.matches) return;

        event.preventDefault();
        result.panel.dataset.spjTotalTouched = 'true';
        result.panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        restoreDirectAccountingDisplay(form);
    }, true);

    const observer = new MutationObserver(() => scheduleSync(form));
    observer.observe(form, { childList: true, subtree: true });
};

const initialize = (root = document) => {
    const form = root.querySelector?.(FORM_SELECTOR) || document.querySelector(FORM_SELECTOR);
    if (form) initializeForm(form);
};

document.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;

    if (matchesAny(input.name, INTEGER_FIELD_PATTERNS)) {
        setupIntegerInput(input);
        validateIntegerMinimum(input);
    }

    if (matchesAny(input.name, DIRECT_ACCOUNTING_FIELD_PATTERNS) && input.type !== 'hidden') {
        setupDirectAccountingInput(input);
        const amount = parseAccounting(input.value);
        input.value = String(amount);
        requestAnimationFrame(() => {
            if (document.contains(input)) input.value = formatAccounting(amount);
        });
    }

    const form = input.closest(FORM_SELECTOR);
    if (form) scheduleSync(form);
}, true);

document.addEventListener('change', (event) => {
    const form = event.target instanceof Element ? event.target.closest(FORM_SELECTOR) : null;
    if (form) scheduleSync(form);
}, true);

document.addEventListener('focusout', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;
    validateIntegerMinimum(input);
    if (input.dataset.spjAccountingInitialized === 'true') {
        input.value = formatAccounting(moneyValue(input.value));
    }
}, true);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initialize());
} else {
    initialize();
}

document.addEventListener('livewire:navigated', () => initialize());

window.SpJDetailInputValidation = {
    initialize,
    formatAccounting,
    parseAccounting,
    normalizeInteger,
};
