const packageManualForm = () => document.querySelector('#spj-manual-form');

const categoryStatus = (form) => {
    let status = form.querySelector('[data-spj-category-status]');
    if (status) return status;

    const select = form.querySelector('#spj-type');
    if (!select) return null;

    status = document.createElement('p');
    status.dataset.spjCategoryStatus = 'true';
    status.className = 'mt-1 text-xs text-[var(--ui-fg-muted)]';
    status.setAttribute('aria-live', 'polite');
    select.insertAdjacentElement('afterend', status);

    return status;
};

const setCategoryStatus = (form, message = '', state = 'idle') => {
    const status = categoryStatus(form);
    if (!status) return;

    status.textContent = message;
    status.classList.remove('text-emerald-700', 'text-rose-700', 'text-[var(--ui-fg-muted)]');

    if (state === 'success') {
        status.classList.add('text-emerald-700');
        return;
    }

    if (state === 'error') {
        status.classList.add('text-rose-700');
        return;
    }

    status.classList.add('text-[var(--ui-fg-muted)]');
};

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

    // Pesanan/BAP/BAST wajib untuk KONSUMSI, tetapi tetap opsional untuk BARANG.
    ['order_date', 'bap_date', 'bast_date'].forEach((name) => {
        const control = form.querySelector(`[data-spj-section~="BARANG"] [name="${name}"]`);
        if (!control || control.disabled) return;
        control.required = category === 'KONSUMSI';
    });
};

const errorMessage = async (response) => {
    try {
        const payload = await response.json();
        if (payload?.message) return payload.message;

        const firstError = Object.values(payload?.errors || {})
            .flat()
            .find(Boolean);
        if (firstError) return firstError;
    } catch (_) {
        // Response bukan JSON; gunakan pesan umum di bawah.
    }

    return 'Kategori SPJ gagal disimpan. Coba lagi.';
};

const persistCategory = async (form, categorySelect, previousCategory) => {
    const csrf = form.querySelector('input[name="_token"]')?.value;
    const category = String(categorySelect.value || '').toUpperCase();

    categorySelect.disabled = true;
    categorySelect.setAttribute('aria-busy', 'true');
    setCategoryStatus(form, 'Menyimpan kategori…');

    const payload = new FormData();
    if (csrf) payload.append('_token', csrf);
    payload.append('_method', 'PUT');
    payload.append('category_switch', '1');
    payload.append('spj_category', category);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(await errorMessage(response));
        }

        const result = await response.json();
        categorySelect.dataset.persistedCategory = result.spj_category || category;
        setCategoryStatus(form, 'Kategori tersimpan tanpa reload halaman.', 'success');

        document.dispatchEvent(new CustomEvent('spj:category-changed', {
            detail: {
                category: categorySelect.dataset.persistedCategory,
                packageId: result.package_id || null,
            },
        }));
    } catch (error) {
        categorySelect.value = previousCategory;
        applyPackageManualCategory();
        setCategoryStatus(form, error?.message || 'Kategori SPJ gagal disimpan. Coba lagi.', 'error');
    } finally {
        categorySelect.disabled = false;
        categorySelect.removeAttribute('aria-busy');
    }
};

const bindPackageManualCategory = () => {
    const form = packageManualForm();
    if (!form || form.dataset.spjCategoryBound === 'true') return;

    const categorySelect = form.querySelector('#spj-type');
    if (!categorySelect) return;

    form.dataset.spjCategoryBound = 'true';
    categorySelect.dataset.persistedCategory = String(categorySelect.value || '').toUpperCase();
    applyPackageManualCategory();

    categorySelect.addEventListener('change', async () => {
        const previousCategory = categorySelect.dataset.persistedCategory || '';
        const selectedCategory = String(categorySelect.value || '').toUpperCase();

        if (selectedCategory === previousCategory) return;

        // Ganti section terlebih dahulu agar respons UI instan, kemudian simpan via AJAX.
        applyPackageManualCategory();
        await persistCategory(form, categorySelect, previousCategory);
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
