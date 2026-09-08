const ACTION_CELL_SELECTOR = '.transaction-action-cell';
const MODAL_ID = 'transaction-action-modal';

const transactionLinks = (cell) => ({
    packageLink: Array.from(cell.querySelectorAll('a[href]')).find((link) => link.getAttribute('title') === 'Buka Paket SPJ') || null,
    detailLink: Array.from(cell.querySelectorAll('a[href]')).find((link) => link.getAttribute('title') === 'Buka detail') || null,
});

const ensureModal = () => {
    let modal = document.getElementById(MODAL_ID);
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = MODAL_ID;
    modal.hidden = true;
    modal.className = 'fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'transaction-action-modal-title');
    modal.innerHTML = `
        <div data-transaction-action-panel class="w-full max-w-md rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">Transaksi</p>
                    <h2 id="transaction-action-modal-title" class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Aksi Transaksi</h2>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Pilih halaman yang ingin dibuka. Data sumber dan uraian item dikelola di Detail Transaksi; data dokumen dikelola di Paket SPJ.</p>
                </div>
                <button type="button" data-transaction-action-close class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--ui-line)] text-lg text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)]" aria-label="Tutup modal">×</button>
            </div>

            <div class="mt-5 grid gap-3">
                <a data-transaction-action-detail href="#" class="flex items-center justify-between gap-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 text-sm font-bold text-[var(--ui-fg-strong)] transition hover:bg-[var(--ui-surface-soft)]">
                    <span>
                        <span class="block">Detail Transaksi</span>
                        <span class="mt-0.5 block text-xs font-normal text-[var(--ui-fg-muted)]">Periksa source ARKAS/BKU dan uraian item untuk SPJ.</span>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
                <a data-transaction-action-package href="#" class="flex items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-bold text-indigo-800 transition hover:bg-indigo-100">
                    <span>
                        <span class="block">Paket SPJ</span>
                        <span class="mt-0.5 block text-xs font-normal text-indigo-700">Siapkan atau buka workspace dokumen SPJ transaksi ini.</span>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            </div>

            <div class="mt-5 flex justify-end border-t border-[var(--ui-line)] pt-4">
                <button type="button" data-transaction-action-close class="ui-btn ui-btn-secondary px-4 py-2 text-sm">Tutup</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const close = () => {
        modal.hidden = true;
        document.body.classList.remove('overflow-hidden');
    };

    modal.querySelectorAll('[data-transaction-action-close]').forEach((button) => {
        button.addEventListener('click', close);
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });
    modal.querySelectorAll('[data-transaction-action-detail], [data-transaction-action-package]').forEach((link) => {
        link.addEventListener('click', close);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) close();
    });

    return modal;
};

const openModalForCell = (cell) => {
    const modal = ensureModal();
    const modalDetail = modal.querySelector('[data-transaction-action-detail]');
    const modalPackage = modal.querySelector('[data-transaction-action-package]');
    const detailUrl = cell.dataset.detailUrl || '';
    const packageUrl = cell.dataset.packageUrl || '';

    if (modalDetail instanceof HTMLAnchorElement) {
        modalDetail.href = detailUrl || '#';
        modalDetail.hidden = !detailUrl;
    }
    if (modalPackage instanceof HTMLAnchorElement) {
        modalPackage.href = packageUrl || '#';
        modalPackage.hidden = !packageUrl;
    }

    modal.hidden = false;
    document.body.classList.add('overflow-hidden');
    window.requestAnimationFrame(() => modal.querySelector('[data-transaction-action-close]')?.focus());
};

const initializeActionCell = (cell) => {
    if (!(cell instanceof HTMLElement) || cell.dataset.transactionActionModalBound === 'true') return;

    const { packageLink, detailLink } = transactionLinks(cell);
    const packageUrl = packageLink?.href || cell.dataset.packageUrl || '';
    const detailUrl = detailLink?.href || cell.dataset.detailUrl || '';

    if (!packageUrl && !detailUrl) return;

    cell.dataset.transactionActionModalBound = 'true';
    cell.dataset.packageUrl = packageUrl;
    cell.dataset.detailUrl = detailUrl;

    packageLink?.remove();
    detailLink?.remove();

    const existingButton = cell.querySelector('[data-transaction-action-trigger]');
    if (existingButton) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.transactionActionTrigger = 'true';
    button.className = 'transaction-action-button transaction-action-edit';
    button.setAttribute('title', 'Tampilkan aksi transaksi');
    button.setAttribute('aria-haspopup', 'dialog');
    button.innerHTML = '<span aria-hidden="true">⋯</span><span>Aksi</span>';
    button.addEventListener('click', () => openModalForCell(cell));
    cell.appendChild(button);
};

const initializeTransactionActionModal = (root = document) => {
    root.querySelectorAll?.(ACTION_CELL_SELECTOR).forEach(initializeActionCell);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeTransactionActionModal(), { once: true });
} else {
    initializeTransactionActionModal();
}

document.addEventListener('livewire:navigated', () => initializeTransactionActionModal());

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) return;
            if (node.matches(ACTION_CELL_SELECTOR)) initializeActionCell(node);
            initializeTransactionActionModal(node);
        });
    });
}).observe(document.body, { childList: true, subtree: true });
