const packageManualForm = () => document.querySelector('#spj-manual-form');

const notify = (type, message) => {
    if (!message) return;
    window.dispatchEvent(new CustomEvent('app-notify', { detail: { type, message } }));
};

const setCategoryStatus = (_form, message = '', state = 'idle') => {
    if (state === 'error') notify('error', message);
    if (state === 'success') notify('success', message);
};

const ensureCategoryContext = (form, categorySelect) => {
    let context = form.querySelector('[data-spj-category-context]');
    if (context instanceof HTMLElement) return context;

    const categoryField = categorySelect.parentElement;
    const categoryGrid = categoryField?.parentElement;
    if (!(categoryField instanceof HTMLElement) || !(categoryGrid instanceof HTMLElement)) return null;

    categoryGrid.classList.add('lg:grid-cols-4', 'lg:items-end');
    categoryField.classList.add('lg:col-span-1', 'min-w-0');

    const hint = Array.from(categoryGrid.children).find((child) => child.tagName === 'P');
    if (hint instanceof HTMLElement) hint.remove();

    context = document.createElement('div');
    context.dataset.spjCategoryContext = 'true';
    context.className = 'min-w-0 lg:col-span-3';
    context.innerHTML = `
        <div data-spj-category-context-panel="BARANG" hidden>
            <div class="flex min-h-10 flex-wrap items-center gap-x-7 gap-y-2 rounded-md border border-amber-300 bg-[var(--ui-surface-base)] px-3 py-1.5 text-sm">
                <label class="inline-flex cursor-pointer items-center gap-2 font-semibold text-[var(--ui-fg-strong)]">
                    <input type="radio" name="spj_siplah_mode_ui" data-spj-siplah-mode value="siplah" class="h-4 w-4 border-amber-300 text-indigo-600 focus:ring-indigo-500">
                    <span>SiPLah</span>
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2 font-semibold text-[var(--ui-fg-strong)]">
                    <input type="radio" name="spj_siplah_mode_ui" data-spj-siplah-mode value="non_siplah" class="h-4 w-4 border-amber-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Non SiPLah</span>
                </label>
            </div>
        </div>
        <div data-spj-category-context-panel="PEMELIHARAAN" hidden>
            <div data-spj-maintenance-quick-slot class="min-w-0"></div>
        </div>
    `;
    categoryGrid.appendChild(context);

    const paymentMethod = form.querySelector('[name="payment_method"]');
    if (paymentMethod instanceof HTMLSelectElement && paymentMethod.value !== 'siplah') {
        form.dataset.lastNonSiplahPayment = paymentMethod.value || 'tunai';
    }

    context.querySelectorAll('[data-spj-siplah-mode]').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (!(radio instanceof HTMLInputElement) || !radio.checked) return;
            if (!(paymentMethod instanceof HTMLSelectElement)) return;

            context.querySelectorAll('[data-spj-siplah-mode]').forEach((otherRadio) => {
                if (otherRadio instanceof HTMLInputElement && otherRadio !== radio) {
                    otherRadio.checked = false;
                }
            });

            if (radio.value === 'siplah') {
                paymentMethod.value = 'siplah';
            } else {
                if (form.dataset.sourceSiplah === '1') {
                    radio.checked = false;
                    const siplahRadio = context.querySelector('[data-spj-siplah-mode="siplah"]');
                    if (siplahRadio instanceof HTMLInputElement) siplahRadio.checked = true;
                    return;
                }
                paymentMethod.value = form.dataset.lastNonSiplahPayment || 'tunai';
            }

            paymentMethod.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    document.dispatchEvent(new CustomEvent('spj:category-context-ready'));
    return context;
};

const syncCategoryContext = (form, categorySelect) => {
    const context = ensureCategoryContext(form, categorySelect);
    if (!(context instanceof HTMLElement)) return;

    const category = String(categorySelect.value || '').toUpperCase();
    context.querySelectorAll('[data-spj-category-context-panel]').forEach((panel) => {
        if (!(panel instanceof HTMLElement)) return;
        const active = panel.dataset.spjCategoryContextPanel === category;
        panel.hidden = !active;
        panel.classList.toggle('hidden', !active);
        panel.setAttribute('aria-hidden', String(!active));
    });

    const paymentMethod = form.querySelector('[name="payment_method"]');
    const sourceSiplah = form.dataset.sourceSiplah === '1';
    const method = paymentMethod instanceof HTMLSelectElement ? paymentMethod.value : '';

    if (paymentMethod instanceof HTMLSelectElement && method && method !== 'siplah') {
        form.dataset.lastNonSiplahPayment = method;
    }

    const siplahRadio = context.querySelector('[data-spj-siplah-mode="siplah"]');
    const nonSiplahRadio = context.querySelector('[data-spj-siplah-mode="non_siplah"]');
    if (siplahRadio instanceof HTMLInputElement) {
        siplahRadio.checked = sourceSiplah || method === 'siplah';
    }
    if (nonSiplahRadio instanceof HTMLInputElement) {
        nonSiplahRadio.checked = !sourceSiplah && method !== 'siplah';
        nonSiplahRadio.disabled = sourceSiplah;
        nonSiplahRadio.closest('label')?.classList.toggle('opacity-40', sourceSiplah);
    }

    if (siplahRadio instanceof HTMLInputElement && nonSiplahRadio instanceof HTMLInputElement) {
        if (siplahRadio.checked) nonSiplahRadio.checked = false;
        if (nonSiplahRadio.checked) siplahRadio.checked = false;
    }
};

const applyPackageManualCategory = () => {
    const form = packageManualForm();
    const categorySelect = form?.querySelector('#spj-type');
    if (!form || !(categorySelect instanceof HTMLSelectElement)) return;

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

    syncCategoryContext(form, categorySelect);
    document.dispatchEvent(new CustomEvent('spj:category-ui-updated', { detail: { category } }));
};

const errorMessage = async (response) => {
    try {
        const payload = await response.json();
        if (payload?.message) return payload.message;

        const firstError = Object.values(payload?.errors || {}).flat().find(Boolean);
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

        if (!response.ok) throw new Error(await errorMessage(response));

        const result = await response.json();
        categorySelect.dataset.persistedCategory = result.spj_category || category;
        categorySelect.value = categorySelect.dataset.persistedCategory;
        applyPackageManualCategory();
        setCategoryStatus(form, 'Kategori SPJ tersimpan.', 'success');
        try {
            await refreshPackagePanels();
        } catch (_) {
            setCategoryStatus(form, 'Kategori tersimpan, tetapi panel dokumen belum dapat diperbarui.', 'error');
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
    if (!(categorySelect instanceof HTMLSelectElement)) return;

    form.dataset.spjCategoryBound = 'true';
    categorySelect.dataset.persistedCategory = String(categorySelect.value || '').toUpperCase();
    ensureCategoryContext(form, categorySelect);
    applyPackageManualCategory();

    const paymentMethod = form.querySelector('[name="payment_method"]');
    paymentMethod?.addEventListener('change', () => {
        if (paymentMethod instanceof HTMLSelectElement && paymentMethod.value !== 'siplah') {
            form.dataset.lastNonSiplahPayment = paymentMethod.value || 'tunai';
        }
        applyPackageManualCategory();
    });

    form.addEventListener('submit', (event) => {
        if (form.dataset.categorySaving === 'true') {
            event.preventDefault();
            event.stopImmediatePropagation();
            notify('warning', 'Tunggu sampai kategori selesai disimpan.');
        }
    }, true);

    categorySelect.addEventListener('change', async () => {
        const previousCategory = categorySelect.dataset.persistedCategory || '';
        const selectedCategory = String(categorySelect.value || '').toUpperCase();
        if (selectedCategory === previousCategory) return;

        applyPackageManualCategory();
        await persistCategory(form, categorySelect, previousCategory);
    });
};

const schedulePackageManualCategory = () => window.requestAnimationFrame(bindPackageManualCategory);

document.addEventListener('DOMContentLoaded', schedulePackageManualCategory);
document.addEventListener('livewire:navigated', schedulePackageManualCategory);

const observer = new MutationObserver((mutations) => {
    if (mutations.some((mutation) => mutation.addedNodes.length > 0)) schedulePackageManualCategory();
});

observer.observe(document.documentElement, { childList: true, subtree: true });
