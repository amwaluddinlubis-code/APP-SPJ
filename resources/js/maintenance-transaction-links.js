const initializeMaintenanceTransactionLinks = async (root = document) => {
    const categorySelect = root.querySelector?.('#detail-spj-type') || document.querySelector('#detail-spj-type');
    if (!(categorySelect instanceof HTMLSelectElement)) return;

    const form = categorySelect.closest('form');
    const panel = categorySelect.closest('.spj-builder-accent-panel');
    if (!(form instanceof HTMLFormElement) || !(panel instanceof HTMLElement)) return;
    if (panel.dataset.maintenanceLinksInitialized === 'true') return;

    const transactionMatch = form.action.match(/\/spj\/(\d+)\/siapkan/);
    if (!transactionMatch) return;

    const transactionId = transactionMatch[1];
    const endpoint = `/transaksi/${transactionId}/pemeliharaan/transaksi-terkait`;
    const categoryColumn = categorySelect.parentElement;
    const originalColumns = Array.from(panel.children).filter((element) => element !== categoryColumn);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const makeColumn = (label, name, placeholder) => {
        const wrapper = document.createElement('div');
        wrapper.hidden = true;
        wrapper.dataset.maintenanceLinkColumn = name;
        wrapper.innerHTML = `
            <label class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-strong)]" for="${name}">${label}</label>
            <select id="${name}" class="ui-select mt-1" aria-label="${label}">
                <option value="">${placeholder}</option>
            </select>
        `;
        panel.appendChild(wrapper);
        return wrapper;
    };

    const materialColumn = makeColumn('Transaksi Bahan / Barang', 'maintenance-material-transaction', 'Pilih transaksi bahan / barang');
    const laborColumn = makeColumn('Transaksi Upah', 'maintenance-labor-transaction', 'Pilih transaksi upah');
    const materialSelect = materialColumn.querySelector('select');
    const laborSelect = laborColumn.querySelector('select');
    let loaded = false;

    const dispatchNotification = (type, message) => {
        window.dispatchEvent(new CustomEvent('app-notify', { detail: { type, message } }));
    };

    const loadCandidates = async () => {
        if (loaded) return;

        const response = await fetch(endpoint, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Daftar transaksi terkait tidak dapat dimuat.');
        }

        const payload = await response.json();
        const options = payload.candidates || [];

        [materialSelect, laborSelect].forEach((select) => {
            options.forEach((candidate) => {
                const option = document.createElement('option');
                option.value = String(candidate.id);
                option.textContent = candidate.label;
                select.appendChild(option);
            });
        });

        materialSelect.value = payload.selected?.material_transaction_id ? String(payload.selected.material_transaction_id) : '';
        laborSelect.value = payload.selected?.labor_transaction_id ? String(payload.selected.labor_transaction_id) : '';
        loaded = true;
    };

    const saveSelection = async () => {
        const response = await fetch(endpoint, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                material_transaction_id: materialSelect.value ? Number(materialSelect.value) : null,
                labor_transaction_id: laborSelect.value ? Number(laborSelect.value) : null,
            }),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = Object.values(payload.errors || {}).flat()[0] || payload.message || 'Transaksi terkait tidak dapat disimpan.';
            throw new Error(message);
        }

        dispatchNotification('success', payload.message || 'Transaksi terkait pemeliharaan berhasil disimpan.');
    };

    const render = async () => {
        const isMaintenance = categorySelect.value.toUpperCase() === 'PEMELIHARAAN';
        originalColumns.forEach((column) => {
            column.hidden = isMaintenance;
        });
        materialColumn.hidden = !isMaintenance;
        laborColumn.hidden = !isMaintenance;

        if (isMaintenance) {
            try {
                await loadCandidates();
            } catch (error) {
                dispatchNotification('error', error.message);
            }
        }
    };

    const persist = async () => {
        try {
            await saveSelection();
        } catch (error) {
            dispatchNotification('error', error.message);
        }
    };

    materialSelect.addEventListener('change', persist);
    laborSelect.addEventListener('change', persist);
    categorySelect.addEventListener('change', render);
    panel.dataset.maintenanceLinksInitialized = 'true';
    render();
};

initializeMaintenanceTransactionLinks();
document.addEventListener('livewire:navigated', () => initializeMaintenanceTransactionLinks());
