const maintenanceBlocks = () => document.querySelectorAll('[data-spj-maintenance-links]');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const categoryIsMaintenance = () => String(document.querySelector('#spj-type')?.value || '').toUpperCase() === 'PEMELIHARAAN';

const errorMessage = async (response, fallback) => {
    try {
        const payload = await response.json();
        const firstError = Object.values(payload?.errors || {}).flat().find(Boolean);
        return firstError || payload?.message || fallback;
    } catch (_) {
        return fallback;
    }
};

const initializeMaintenanceBlock = (block) => {
    if (block.dataset.maintenanceLinksBound === 'true') return;

    const materialWrapper = block.querySelector('[data-maintenance-link-field="material"]');
    const laborWrapper = block.querySelector('[data-maintenance-link-field="labor"]');
    const materialSelect = block.querySelector('[data-maintenance-link-select="material"]');
    const laborSelect = block.querySelector('[data-maintenance-link-select="labor"]');
    const roleText = block.querySelector('[data-maintenance-link-role]');
    const originalStatus = block.querySelector('[data-maintenance-link-status]');
    const quickSlot = document.querySelector('[data-spj-maintenance-quick-slot]');
    const quickStatus = document.querySelector('[data-spj-maintenance-quick-status]');
    const categorySelect = document.querySelector('#spj-type');

    if (!(materialSelect instanceof HTMLSelectElement)
        || !(laborSelect instanceof HTMLSelectElement)
        || !(materialWrapper instanceof HTMLElement)
        || !(laborWrapper instanceof HTMLElement)) return;

    block.dataset.maintenanceLinksBound = 'true';

    if (quickSlot instanceof HTMLElement) {
        const materialLabel = materialWrapper.querySelector('label');
        const laborLabel = laborWrapper.querySelector('label');
        if (materialLabel instanceof HTMLElement) materialLabel.textContent = 'Bahan';
        if (laborLabel instanceof HTMLElement) laborLabel.textContent = 'Upah';

        materialWrapper.querySelectorAll('p').forEach((hint) => { hint.hidden = true; });
        laborWrapper.querySelectorAll('p').forEach((hint) => { hint.hidden = true; });
        materialWrapper.classList.add('min-w-0');
        laborWrapper.classList.add('min-w-0');
        quickSlot.append(materialWrapper, laborWrapper);

        // Panel linkage lama tetap menjadi holder endpoint/state, tetapi UI selector dipindah
        // ke baris kategori seperti versi sebelumnya.
        block.hidden = true;
        block.classList.add('hidden');
        block.setAttribute('aria-hidden', 'true');
    }

    const editable = block.dataset.editable === '1';
    let loaded = false;
    let loading = false;
    let saving = false;
    let currentRole = 'unknown';
    let lastSaved = { material: '', labor: '' };

    const statusElement = quickStatus instanceof HTMLElement ? quickStatus : originalStatus;
    const setStatus = (message = '', state = 'idle') => {
        if (!(statusElement instanceof HTMLElement)) return;
        statusElement.textContent = message;
        statusElement.classList.remove('text-emerald-700', 'text-rose-700', 'text-[var(--ui-fg-muted)]');
        statusElement.classList.add(state === 'success'
            ? 'text-emerald-700'
            : (state === 'error' ? 'text-rose-700' : 'text-[var(--ui-fg-muted)]'));
    };

    const resetOptions = (select, placeholder) => {
        select.replaceChildren();
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    };

    const syncExclusiveOptions = () => {
        const materialValue = materialSelect.value;
        const laborValue = laborSelect.value;

        Array.from(materialSelect.options).forEach((option) => {
            option.disabled = option.value !== '' && option.value === laborValue;
        });
        Array.from(laborSelect.options).forEach((option) => {
            option.disabled = option.value !== '' && option.value === materialValue;
        });
    };

    const renderRole = () => {
        const active = categoryIsMaintenance();
        const materialHidden = !active || currentRole === 'material';
        const laborHidden = !active || currentRole === 'labor';

        materialWrapper.hidden = materialHidden;
        laborWrapper.hidden = laborHidden;
        materialWrapper.classList.toggle('md:col-span-2', !materialHidden && laborHidden);
        laborWrapper.classList.toggle('md:col-span-2', !laborHidden && materialHidden);

        materialSelect.disabled = materialHidden || !editable || loading || saving;
        laborSelect.disabled = laborHidden || !editable || loading || saving;

        if (quickSlot instanceof HTMLElement) {
            quickSlot.hidden = !active;
            quickSlot.classList.toggle('hidden', !active);
        }

        if (roleText instanceof HTMLElement) {
            roleText.textContent = currentRole === 'material'
                ? 'Transaksi ini terdeteksi sebagai bahan/barang. Pilih transaksi upah yang menjadi pasangan pekerjaan.'
                : (currentRole === 'labor'
                    ? 'Transaksi ini terdeteksi sebagai upah. Pilih transaksi bahan/barang yang menjadi pasangan pekerjaan.'
                    : 'Peran transaksi belum dapat ditentukan otomatis. Pilih transaksi bahan/barang dan/atau upah yang terkait.');
        }
    };

    const populate = (payload) => {
        const candidates = Array.isArray(payload?.candidates) ? payload.candidates : [];
        currentRole = payload?.current_role || 'unknown';

        resetOptions(materialSelect, candidates.length ? 'Pilih transaksi bahan / barang' : 'Tidak ada transaksi bahan/barang yang memenuhi syarat');
        resetOptions(laborSelect, candidates.length ? 'Pilih transaksi upah' : 'Tidak ada transaksi upah yang memenuhi syarat');

        [materialSelect, laborSelect].forEach((select) => {
            candidates.forEach((candidate) => {
                const option = document.createElement('option');
                option.value = String(candidate.id);
                option.textContent = candidate.label;
                select.appendChild(option);
            });
        });

        materialSelect.value = payload?.selected?.material_transaction_id
            ? String(payload.selected.material_transaction_id)
            : '';
        laborSelect.value = payload?.selected?.labor_transaction_id
            ? String(payload.selected.labor_transaction_id)
            : '';

        if (currentRole === 'material') materialSelect.value = '';
        if (currentRole === 'labor') laborSelect.value = '';

        lastSaved = { material: materialSelect.value, labor: laborSelect.value };
        syncExclusiveOptions();
        renderRole();
    };

    const load = async () => {
        if (loaded || loading || !categoryIsMaintenance()) {
            renderRole();
            return;
        }

        loading = true;
        renderRole();
        setStatus('Memuat transaksi terkait…');

        try {
            const response = await fetch(block.dataset.showUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(await errorMessage(response, 'Daftar transaksi terkait tidak dapat dimuat.'));
            }

            populate(await response.json());
            loaded = true;
            setStatus(editable
                ? 'Transaksi terkait siap dipilih.'
                : 'Transaksi terkait hanya dapat diubah saat Paket SPJ masih editable.');
        } catch (error) {
            setStatus(error?.message || 'Daftar transaksi terkait tidak dapat dimuat.', 'error');
        } finally {
            loading = false;
            renderRole();
        }
    };

    const save = async () => {
        if (!editable || saving || !loaded) return;

        saving = true;
        syncExclusiveOptions();
        renderRole();
        setStatus('Menyimpan transaksi terkait…');

        const payload = {
            material_transaction_id: currentRole === 'material' || !materialSelect.value
                ? null
                : Number(materialSelect.value),
            labor_transaction_id: currentRole === 'labor' || !laborSelect.value
                ? null
                : Number(laborSelect.value),
        };

        try {
            const response = await fetch(block.dataset.updateUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(await errorMessage(response, 'Transaksi terkait tidak dapat disimpan.'));
            }

            lastSaved = { material: materialSelect.value, labor: laborSelect.value };
            syncExclusiveOptions();
            setStatus('Transaksi terkait pemeliharaan tersimpan.', 'success');
        } catch (error) {
            materialSelect.value = lastSaved.material;
            laborSelect.value = lastSaved.labor;
            syncExclusiveOptions();
            setStatus(error?.message || 'Transaksi terkait tidak dapat disimpan.', 'error');
        } finally {
            saving = false;
            renderRole();
        }
    };

    materialSelect.addEventListener('change', save);
    laborSelect.addEventListener('change', save);
    categorySelect?.addEventListener('change', () => {
        renderRole();
        if (categoryIsMaintenance()) load();
    });
    document.addEventListener('spj:category-changed', () => {
        renderRole();
        if (categoryIsMaintenance()) load();
    });

    renderRole();
    if (categoryIsMaintenance()) load();
};

const initializeMaintenanceLinks = () => maintenanceBlocks().forEach(initializeMaintenanceBlock);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMaintenanceLinks, { once: true });
} else {
    initializeMaintenanceLinks();
}

document.addEventListener('livewire:navigated', initializeMaintenanceLinks);
