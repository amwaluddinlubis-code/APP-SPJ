const findCategoryFieldset = (form, category) => Array.from(form.querySelectorAll('fieldset')).find((fieldset) =>
    (fieldset.getAttribute('x-show') || '').includes(`category === '${category}'`));

const arrangeCategoryControls = (form) => {
    const categorySelect = form.querySelector('#detail-spj-type');
    if (!(categorySelect instanceof HTMLSelectElement)) return;

    const panel = categorySelect.closest('.spj-builder-accent-panel');
    const categoryField = categorySelect.closest('div');
    if (!(panel instanceof HTMLElement) || !(categoryField instanceof HTMLElement)) return;

    panel.classList.remove('lg:grid-cols-3');
    panel.classList.add('lg:grid-cols-4');
    categoryField.classList.add('lg:col-span-1');

    Array.from(panel.children).forEach((child) => {
        if (!(child instanceof HTMLElement) || child === categoryField) return;
        const condition = child.getAttribute('x-show') || '';
        if (condition.includes("category === 'BARANG'") || condition.includes("category === 'PEMELIHARAAN'")) {
            child.classList.remove('col-span-3');
            child.classList.add('lg:col-span-3');
        }
    });
};

const syncSiplahFields = (form) => {
    const categorySelect = form.querySelector('#detail-spj-type');
    const paymentMethod = form.querySelector('[name="payment_method"]');
    if (!(categorySelect instanceof HTMLSelectElement) || !(paymentMethod instanceof HTMLSelectElement)) return;

    const siplahFieldset = Array.from(form.querySelectorAll('fieldset')).find((fieldset) => {
        const condition = fieldset.getAttribute('x-show') || '';
        return condition.includes("paymentMethod === 'siplah'") && fieldset.textContent?.includes('Data Pembelian SiPLah');
    });
    if (!(siplahFieldset instanceof HTMLFieldSetElement)) return;

    const showSiplahFields = categorySelect.value.toUpperCase() === 'BARANG' && paymentMethod.value.toLowerCase() === 'siplah';
    siplahFieldset.hidden = !showSiplahFields;
    siplahFieldset.disabled = !showSiplahFields;
    siplahFieldset.setAttribute('aria-hidden', showSiplahFields ? 'false' : 'true');
};

const syncFullWidthCategorySections = (form) => {
    const categorySelect = form.querySelector('#detail-spj-type');
    const fullWidth = form.querySelector('.transaction-detail-fullwidth-sections');
    if (!(categorySelect instanceof HTMLSelectElement) || !(fullWidth instanceof HTMLElement)) return;

    const travelFieldset = findCategoryFieldset(form, 'SPPD');
    if (travelFieldset instanceof HTMLFieldSetElement && travelFieldset.parentElement !== fullWidth) {
        fullWidth.append(travelFieldset);
    }

    const category = categorySelect.value.toUpperCase();
    const fullWidthCategories = ['PEMELIHARAAN', 'HONOR_PEGAWAI', 'JASA_LAINNYA', 'SPPD'];
    const shouldShow = fullWidthCategories.includes(category);
    if (fullWidth.hidden === shouldShow) fullWidth.hidden = !shouldShow;
};

const normalizeTransactionCategoryLayout = () => {
    const form = document.querySelector('form[action*="/spj/"][action*="/siapkan"]');
    if (!(form instanceof HTMLFormElement)) return;

    arrangeCategoryControls(form);
    syncSiplahFields(form);
    syncFullWidthCategorySections(form);

    if (form.dataset.transactionCategoryLayoutBound === '1') return;
    form.dataset.transactionCategoryLayoutBound = '1';

    ['spj_category', 'payment_method'].forEach((name) => {
        form.querySelector(`[name="${name}"]`)?.addEventListener('change', () => {
            requestAnimationFrame(() => requestAnimationFrame(normalizeTransactionCategoryLayout));
        });
    });

    const observer = new MutationObserver(() => {
        requestAnimationFrame(normalizeTransactionCategoryLayout);
    });
    const fullWidth = form.querySelector('.transaction-detail-fullwidth-sections');
    if (fullWidth instanceof HTMLElement) {
        observer.observe(fullWidth, { attributes: true, attributeFilter: ['hidden'], childList: true });
    }
};

const bootTransactionCategoryLayout = () => {
    requestAnimationFrame(() => requestAnimationFrame(normalizeTransactionCategoryLayout));
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTransactionCategoryLayout, { once: true });
} else {
    bootTransactionCategoryLayout();
}

document.addEventListener('livewire:navigated', bootTransactionCategoryLayout);
