const maintenanceBlocks = () => document.querySelectorAll('[data-spj-maintenance-links]');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const categoryIsMaintenance = () => String(document.querySelector('#spj-type')?.value || '').toUpperCase() === 'PEMELIHARAAN';

const notify = (type, message) => {
    window.dispatchEvent(new CustomEvent('app-notify', { detail: { type, message } }));
};

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
    if (!(block instanceof HTMLElement) || block.dataset.maintenanceLinksBound === 'true') return;

    const quickSlot = document.querySelector('[data-spj-maintenance-quick-slot]');
    const categorySelect = document.querySelector('#spj-type');
    if (!(quickSlot instanceof HTMLElement) || !(categorySelect instanceof HTMLSelectElement)) return;

    block.dataset.maintenanceLinksBound = 'true';
    block.hidden = true;

    quickSlot.replaceChildren();
    quickSlot.className = 'min-w-0';

    const wrapper = document.createElement('div');
    wrapper.className = 'grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-end gap-2';
    wrapper.innerHTML = `
        <label data-maintenance-quick-label class="pb-2 text-xs font-bold text-amber-900">Bahan</label>
        <select data-maintenance-quick-select class="ui-select w-full !py-1.5 !text-sm" disabled>
            <option value="">Memuat transaksi…</option>
        </select>
    `;
    quickSlot.appendChild(wrapper);

    const label = wrapper.querySelector('[data-maintenance-quick-label]');
    const select = wrapper.querySelector('[data-maintenance-quick-select]');
    if (!(label instanceof HTMLElement) || !(select instanceof HTMLSelectElement)) return;

    const editable = block.dataset.editable === '1';
    let loaded = false;
    let loading = false;
    let saving = false;
    let currentRole = 'unknown';
    let selectedMaterial = '';
    let selectedLabor = '';
    let lastSavedValue = '';

    const selectedRole = () => currentRole === 'material' ? 'labor' : 'material';

    const render = () => {
        const active = categoryIsMaintenance();
        quickSlot.hidden = !active;
        quickSlot.classList.toggle('hidden', !active);

        const role = selectedRole();
        label.textContent = role === 'labor' ? 'Upah' : 'Bahan';
        select.disabled = !active || !editable || loading || saving;
    };

    const resetOptions = (placeholder) => {
        select.replaceChildren();
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    };

    const populate = (payload) => {
        const candidates = Array.isArray(payload?.candidates) ? payload.candidates : [];
        currentRole = payload?.current_role || 'unknown';
        selectedMaterial = payload?.selected?.material_transaction_id ? String(payload.selected.material_transaction_id) : '';
        selectedLabor = payload?.selected?.labor_transaction_id ? String(payload.selected.labor_transaction_id) : '';

        const role = selectedRole();
        resetOptions(candidates.length
            ? (role === 'labor' ? 'Pilih transaksi upah' : 'Pilih transaksi bahan')
            : 'Tidak ada transaksi yang memenuhi syarat');

        candidates.forEach((candidate) => {
            const option = document.createElement('option');
            option.value = String(candidate.id);
            option.textContent = candidate.label;
            select.appendChild(option);
        });

        select.value = role === 'labor' ? selectedLabor : selectedMaterial;
        lastSavedValue = select.value;
        render();
    };

    const load = async () => {
        if (loaded || loading || !categoryIsMaintenance()) {
            render();
            return;
        }

        loading = true;
        render();
        resetOptions('Memuat transaksi…');

        try {
            const response = await fetch(block.dataset.showUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(await errorMessage(response, 'Daftar transaksi terkait tidak dapat dimuat.'));

            populate(await response.json());
            loaded = true;
        } catch (error) {
            resetOptions('Gagal memuat transaksi');
            notify('error', error?.message || 'Daftar transaksi terkait tidak dapat dimuat.');
        } finally {
            loading = false;
            render();
        }
    };

    const save = async () => {
        if (!editable || !loaded || saving) return;

        saving = true;
        render();
        const role = selectedRole();
        const value = select.value;
        const payload = {
            material_transaction_id: role === 'material' && value ? Number(value) : null,
            labor_transaction_id: role === 'labor' && value ? Number(value) : null,
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
            if (!response.ok) throw new Error(await errorMessage(response, 'Transaksi terkait tidak dapat disimpan.'));

            if (role === 'material') selectedMaterial = value;
            else selectedLabor = value;
            lastSavedValue = value;
            notify('success', role === 'material' ? 'Transaksi bahan tersimpan.' : 'Transaksi upah tersimpan.');
        } catch (error) {
            select.value = lastSavedValue;
            notify('error', error?.message || 'Transaksi terkait tidak dapat disimpan.');
        } finally {
            saving = false;
            render();
        }
    };

    select.addEventListener('change', save);
    categorySelect.addEventListener('change', () => {
        render();
        if (categoryIsMaintenance()) load();
    });
    document.addEventListener('spj:category-changed', () => {
        render();
        if (categoryIsMaintenance()) load();
    });

    render();
    if (categoryIsMaintenance()) load();
};

const initializeMaintenanceLinks = () => {
    window.requestAnimationFrame(() => maintenanceBlocks().forEach(initializeMaintenanceBlock));
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMaintenanceLinks, { once: true });
} else {
    initializeMaintenanceLinks();
}

document.addEventListener('livewire:navigated', initializeMaintenanceLinks);
