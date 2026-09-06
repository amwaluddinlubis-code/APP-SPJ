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

            if (!active) {
                control.disabled = true;
                return;
            }

            control.disabled = control.getAttribute('data-spj-category-original-disabled') === '1';
        });
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

        // Category change is persisted first, then the server reloads the package
        // so server-rendered category-specific fields match Transaction Detail.
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
