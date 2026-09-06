const packageManualForm = () => document.querySelector('#spj-manual-form');

const applyPackageManualCategory = () => {
    const form = packageManualForm();
    if (!form) return;

    const categorySelect = form.querySelector('#spj-type');
    if (!categorySelect) return;

    const category = String(categorySelect.value || '').toUpperCase();

    form.querySelectorAll('[data-spj-section]').forEach((section) => {
        const categories = String(section.dataset.spjSection || '')
            .split(/\s+/)
            .map((value) => value.trim().toUpperCase())
            .filter(Boolean);
        const active = categories.includes(category);

        section.hidden = !active;
        section.setAttribute('aria-hidden', active ? 'false' : 'true');

        section.querySelectorAll('input, select, textarea, button').forEach((control) => {
            if (!control.hasAttribute('data-spj-category-original-disabled')) {
                control.setAttribute('data-spj-category-original-disabled', control.disabled ? '1' : '0');
            }
            if (!control.hasAttribute('data-spj-category-original-required')) {
                control.setAttribute('data-spj-category-original-required', control.required ? '1' : '0');
            }

            if (!active) {
                control.disabled = true;
                control.required = false;
                return;
            }

            control.disabled = control.getAttribute('data-spj-category-original-disabled') === '1';
            control.required = control.getAttribute('data-spj-category-original-required') === '1';
        });
    });

    // Detail Transaksi treats Pesanan/BAP/BAST dates as optional for BARANG,
    // but required for KONSUMSI. Keep Paket → Isian Manual identical.
    ['order_date', 'bap_date', 'bast_date'].forEach((name) => {
        const control = form.querySelector(`[data-spj-section~="BARANG"] [name="${name}"]`);
        if (!control || control.disabled) return;
        control.required = category === 'KONSUMSI';
    });
};

const bindPackageManualCategory = () => {
    const form = packageManualForm();
    if (!form || form.dataset.spjCategoryBound === 'true') return;

    const categorySelect = form.querySelector('#spj-type');
    if (!categorySelect) return;

    form.dataset.spjCategoryBound = 'true';
    applyPackageManualCategory();

    categorySelect.addEventListener('change', () => {
        applyPackageManualCategory();

        let switchField = form.querySelector('input[name="category_switch"]');
        if (!switchField) {
            switchField = document.createElement('input');
            switchField.type = 'hidden';
            switchField.name = 'category_switch';
            form.appendChild(switchField);
        }
        switchField.value = '1';

        // Persist the category first and reload the server-rendered package form.
        // submit() intentionally bypasses stale required controls from the old category.
        form.submit();
    });
};

const schedulePackageManualCategory = () => window.requestAnimationFrame(bindPackageManualCategory);

document.addEventListener('DOMContentLoaded', schedulePackageManualCategory);
document.addEventListener('livewire:navigated', schedulePackageManualCategory);

const observer = new MutationObserver((mutations) => {
    if (mutations.some((mutation) => mutation.addedNodes.length > 0)) {
        schedulePackageManualCategory();
    }
});

observer.observe(document.documentElement, { childList: true, subtree: true });
