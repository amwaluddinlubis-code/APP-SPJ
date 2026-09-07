const packageForm = () => document.querySelector('#spj-manual-form');

const addOwnershipNotice = (form) => {
    if (form.querySelector('[data-spj-ownership-notice]')) return;

    const notice = document.createElement('div');
    notice.dataset.spjOwnershipNotice = 'true';
    notice.className = 'rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-2.5 text-sm text-[var(--ui-fg)]';
    notice.innerHTML = `
        <p class="font-bold text-[var(--ui-fg-strong)]">Paket SPJ = data dokumen pertanggungjawaban</p>
        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">
            Isi kategori, uraian dokumen, penerima, vendor, dan data sesuai kategori di sini.
            Nilai transaksi, PPN/PPh/SSPD, serta uraian item berasal dari Detail Transaksi dan hanya dibaca oleh Paket SPJ.
        </p>
    `;

    form.prepend(notice);
};

const normalizeValidationLinks = () => {
    const headings = Array.from(document.querySelectorAll('h2'));
    const heading = headings.find((node) => node.textContent?.trim() === 'Validasi Sebelum Cetak');
    const section = heading?.closest('section');
    if (!section) return;

    section.querySelectorAll('a[href]').forEach((link) => {
        const href = link.getAttribute('href') || '';
        if (href.includes('/spj') && href.includes('tab=paket')) {
            link.textContent = 'Lengkapi di Paket SPJ';
            return;
        }
        if (href.includes('/transaksi/')) {
            link.textContent = 'Periksa Detail Transaksi';
        }
    });
};

const applyPackageTransactionBoundary = () => {
    const form = packageForm();
    if (!form) return;

    addOwnershipNotice(form);
    normalizeValidationLinks();
};

const schedule = () => window.requestAnimationFrame(applyPackageTransactionBoundary);

document.addEventListener('DOMContentLoaded', schedule);
document.addEventListener('livewire:navigated', schedule);

const observer = new MutationObserver((mutations) => {
    if (mutations.some((mutation) => mutation.addedNodes.length > 0)) {
        schedule();
    }
});

observer.observe(document.documentElement, { childList: true, subtree: true });
