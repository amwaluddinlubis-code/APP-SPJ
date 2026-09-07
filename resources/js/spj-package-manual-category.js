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
    const categorySelect = form?.querySelector('#spj-type');
    if (!categorySelect) return;

    const category = String(categorySelect.value || '').toUpperCase();
    form.querySelectorAll('fieldset[data-spj-section]').forEach((section) => {
        const active = section.dataset.spjSection.split(/\s+/).includes(category);
        section.hidden = !active;
        section.classList.toggle('hidden', !active);
        section.disabled = !active;
        section.setAttribute('aria-hidden', String(!active));
    });

    const paymentMethod = form.querySelector('[name="payment_method"]')?.value;
    const isSiplah = paymentMethod === 'siplah' || form.dataset.sourceSiplah === '1';
    form.querySelectorAll('[data-spj-procurement]').forEach((section) => {
        const active = section.dataset.spjProcurement === 'siplah'
            ? category === 'BARANG' && isSiplah
            : !isSiplah;
        section.hidden = !active;
        section.disabled = !active;
    });
    ['payment_reference', 'invoice_number', 'invoice_date'].forEach((name) => {
        const control = form.querySelector('[name="' + name + '"]');
        if (control) control.required = isSiplah;
    });
    ['order_date', 'bap_date', 'bast_date'].forEach((name) => {
        const control = form.querySelector('[name="' + name + '"]');
        if (control) control.required = category === 'KONSUMSI' && !isSiplah;
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

const refreshPackagePanels = async () => {
    const response = await fetch(window.location.href, {
        credentials: 'same-origin',
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
    });
    if (!response.ok || response.redirected) throw new Error('Panel Paket gagal dimuat.');
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const updates = ['validation', 'documents', 'numbering'].map((name) => {
        const selector = '[data-spj-refresh="' + name + '"]';
        const current = document.querySelector(selector);
        const fresh = page.querySelector(selector);
        if (!current || !fresh) throw new Error('Panel Paket tidak tersedia.');
        return { current, fresh };
    });
    updates.forEach(({ current, fresh }) => current.replaceChildren(...fresh.childNodes));
};

const setPanelsBusy = (busy) => {
    document.querySelectorAll('[data-spj-refresh]').forEach((panel) => {
        panel.inert = busy;
        panel.setAttribute('aria-busy', String(busy));
    });
};

const persistCategory = async (form, categorySelect, previousCategory) => {
    const csrf = form.querySelector('input[name="_token"]')?.value;
    const category = String(categorySelect.value || '').toUpperCase();

    categorySelect.disabled = true;
    form.dataset.categorySaving = 'true';
    setPanelsBusy(true);
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
        categorySelect.value = categorySelect.dataset.persistedCategory;
        applyPackageManualCategory();
        setCategoryStatus(form, 'Kategori tersimpan tanpa reload halaman.', 'success');
        try {
            await refreshPackagePanels();
        } catch (_) {
            setCategoryStatus(form, 'Kategori tersimpan. Panel dokumen belum diperbarui; simpan isian untuk memuat ulang panel.', 'error');
        } finally {
            setPanelsBusy(false);
        }

        document.dispatchEvent(new CustomEvent('spj:category-changed', {
            detail: {
                category: categorySelect.dataset.persistedCategory,
                packageId: result.package_id || null,
            },
        }));
    } catch (error) {
        categorySelect.value = previousCategory;
        applyPackageManualCategory();
        setPanelsBusy(false);
        setCategoryStatus(form, error?.message || 'Kategori SPJ gagal disimpan. Coba lagi.', 'error');
    } finally {
        form.dataset.categorySaving = 'false';
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
    form.querySelector('[name="payment_method"]')?.addEventListener('change', applyPackageManualCategory);
    form.addEventListener('submit', (event) => {
        if (form.dataset.categorySaving === 'true') {
            event.preventDefault();
            event.stopImmediatePropagation();
            setCategoryStatus(form, 'Tunggu sampai kategori selesai disimpan.');
        }
    }, true);

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
