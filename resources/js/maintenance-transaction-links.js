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
            <select id="${name}" class="ui-select mt-1" aria-label="${label}" disabled>
                <option value="">${placeholder}</option>
            </select>
            <p class="mt-1 text-[11px] leading-relaxed text-[var(--ui-fg-muted)]">Format: nomor bukti - uraian pembayaran.</p>
        `;
        panel.appendChild(wrapper);
        return wrapper;
    };

    const materialColumn = makeColumn('Transaksi Bahan / Barang', 'maintenance-material-transaction', 'Pilih transaksi bahan / barang');
    const laborColumn = makeColumn('Transaksi Upah', 'maintenance-labor-transaction', 'Pilih transaksi upah');
    const materialSelect = materialColumn.querySelector('select');
    const laborSelect = laborColumn.querySelector('select');
    let currentRole = 'unknown';
    let loaded = false;
    let loading = false;
    let saving = false;

    if (!(materialSelect instanceof HTMLSelectElement) || !(laborSelect instanceof HTMLSelectElement)) return;

    const dispatchNotification = (type, message) => {
        window.dispatchEvent(new CustomEvent('app-notify', { detail: { type, message } }));
    };

    const setControlsDisabled = (disabled) => {
        const fieldsetDisabled = Boolean(panel.closest('fieldset')?.disabled);
        materialSelect.disabled = disabled || fieldsetDisabled;
        laborSelect.disabled = disabled || fieldsetDisabled;
    };

    const resetOptions = (select, placeholder) => {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    };

    const synchronizeExclusiveOptions = () => {
        const materialValue = materialSelect.value;
        const laborValue = laborSelect.value;

        Array.from(materialSelect.options).forEach((option) => {
            option.disabled = option.value !== '' && option.value === laborValue;
        });
        Array.from(laborSelect.options).forEach((option) => {
            option.disabled = option.value !== '' && option.value === materialValue;
        });
    };

    const renderMaintenanceColumns = (isMaintenance) => {
        if (!isMaintenance || !loaded) {
            materialColumn.hidden = true;
            laborColumn.hidden = true;
            return;
        }

        materialColumn.hidden = currentRole === 'material';
        laborColumn.hidden = currentRole === 'labor';
    };

    const loadCandidates = async () => {
        if (loaded || loading) return;

        loading = true;
        setControlsDisabled(true);
        resetOptions(materialSelect, 'Memuat transaksi bahan / barang...');
        resetOptions(laborSelect, 'Memuat transaksi upah...');

        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Daftar transaksi terkait tidak dapat dimuat.');
            }

            const payload = await response.json();
            const options = payload.candidates || [];
            currentRole = payload.current_role || 'unknown';

            resetOptions(materialSelect, options.length ? 'Pilih transaksi bahan / barang' : 'Tidak ada transaksi yang memenuhi syarat');
            resetOptions(laborSelect, options.length ? 'Pilih transaksi upah' : 'Tidak ada transaksi yang memenuhi syarat');

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

            if (currentRole === 'labor') {
                laborSelect.value = '';
            } else if (currentRole === 'material') {
                materialSelect.value = '';
            }

            synchronizeExclusiveOptions();
            loaded = true;
        } finally {
            loading = false;
            setControlsDisabled(false);
        }
    };

    const saveSelection = async () => {
        if (saving) return;

        saving = true;
        setControlsDisabled(true);

        try {
            const response = await fetch(endpoint, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    material_transaction_id: currentRole === 'material' || !materialSelect.value ? null : Number(materialSelect.value),
                    labor_transaction_id: currentRole === 'labor' || !laborSelect.value ? null : Number(laborSelect.value),
                }),
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = Object.values(payload.errors || {}).flat()[0] || payload.message || 'Transaksi terkait tidak dapat disimpan.';
                throw new Error(message);
            }

            synchronizeExclusiveOptions();
            dispatchNotification('success', payload.message || 'Transaksi terkait pemeliharaan berhasil disimpan.');
        } finally {
            saving = false;
            setControlsDisabled(false);
        }
    };

    const render = async () => {
        const isMaintenance = categorySelect.value.toUpperCase() === 'PEMELIHARAAN';

        originalColumns.forEach((column) => {
            column.hidden = isMaintenance;
        });
        renderMaintenanceColumns(isMaintenance);

        if (!isMaintenance) return;

        try {
            await loadCandidates();
            renderMaintenanceColumns(true);
        } catch (error) {
            resetOptions(materialSelect, 'Gagal memuat transaksi');
            resetOptions(laborSelect, 'Gagal memuat transaksi');
            setControlsDisabled(true);
            dispatchNotification('error', error.message);
        }
    };

    const persist = async () => {
        synchronizeExclusiveOptions();

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
