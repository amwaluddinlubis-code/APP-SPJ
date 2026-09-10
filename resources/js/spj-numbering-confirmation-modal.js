const NUMBERING_FORM_SELECTOR = [
    'form[data-confirm][action*="/spj/paket/"][action$="/nomor"]',
    'form[data-confirm][action*="/spj/dokumen/"][action$="/ganti"]',
].join(',');

let pendingForm = null;
let pendingSubmitter = null;
let pendingTrigger = null;

const findOldNumber = (form) => {
    const message = form.dataset.confirm || '';
    const messageMatch = message.match(/Nomor lama\s+(.+?)\s+akan/i);
    if (messageMatch?.[1]) return messageMatch[1].trim();

    const scopes = [form.closest('.p-3'), form.closest('.rounded-lg'), form.parentElement];
    for (const scope of scopes) {
        const number = scope?.querySelector?.('.font-mono')?.textContent?.trim();
        if (number) return number;
    }

    return '';
};

const modalCopy = (form) => {
    const action = form.action || '';
    const replacingDocument = action.includes('/spj/dokumen/') && action.endsWith('/ganti');

    return replacingDocument
        ? {
            kicker: 'PENOMORAN ULANG DOKUMEN',
            title: 'Konfirmasi penomoran ulang',
            accept: 'Konfirmasi & Nomori Ulang',
            note: 'Nomor lama akan tetap tersimpan dalam riwayat. Sistem menerbitkan dokumen pengganti sesuai aturan penomoran yang aktif.',
        }
        : {
            kicker: 'PENERBITAN ULANG NOMOR SPJ',
            title: 'Konfirmasi penerbitan nomor pengganti',
            accept: 'Konfirmasi & Terbitkan Nomor',
            note: 'Nomor yang dibatalkan tetap tercatat dalam riwayat. Sistem akan menentukan nomor pengganti sesuai urutan dan format penomoran yang aktif.',
        };
};

const ensureModal = () => {
    let modal = document.getElementById('spj-numbering-confirmation-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'spj-numbering-confirmation-modal';
    modal.hidden = true;
    modal.className = 'fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'spj-numbering-confirmation-title');
    modal.innerHTML = `
        <div data-numbering-confirmation-panel class="w-full max-w-lg overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-[var(--ui-line)] px-5 py-4">
                <div>
                    <p data-numbering-confirmation-kicker class="text-xs font-bold uppercase tracking-wide text-[var(--theme-content-accent)]"></p>
                    <h2 id="spj-numbering-confirmation-title" data-numbering-confirmation-title class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]"></h2>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Periksa kembali sebelum sistem membuat nomor baru.</p>
                </div>
                <button type="button" data-numbering-confirmation-close class="rounded-md px-2 py-1 text-xl text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-fg-strong)]" aria-label="Tutup">×</button>
            </div>

            <div class="space-y-4 px-5 py-5">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p data-numbering-confirmation-message class="font-semibold"></p>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div data-numbering-confirmation-old-wrapper class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nomor lama</dt>
                        <dd data-numbering-confirmation-old class="mt-1 break-all font-mono text-sm font-bold text-rose-700"></dd>
                    </div>
                    <div data-numbering-confirmation-reason-wrapper class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Alasan</dt>
                        <dd data-numbering-confirmation-reason class="mt-1 text-sm font-semibold text-[var(--ui-fg-strong)]"></dd>
                    </div>
                </dl>

                <div class="rounded-xl border border-[var(--theme-accent-soft)] bg-[var(--theme-accent-soft)] px-4 py-3 text-xs leading-relaxed text-[var(--ui-fg-strong)]">
                    <p data-numbering-confirmation-note></p>
                    <p class="mt-1 font-semibold">Permintaan tetap diproses melalui aturan server; modal ini tidak melewati validasi backend.</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4">
                <button type="button" data-numbering-confirmation-close class="ui-btn ui-btn-secondary px-4 py-2 text-sm">Kembali periksa</button>
                <button type="button" data-numbering-confirmation-accept class="ui-btn ui-btn-primary px-4 py-2 text-sm"></button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const close = () => {
        if (!pendingForm) {
            modal.hidden = true;
            return;
        }

        delete pendingForm.dataset.confirmed;
        modal.hidden = true;
        document.body.classList.remove('overflow-hidden');
        pendingTrigger?.focus?.();
        pendingForm = null;
        pendingSubmitter = null;
        pendingTrigger = null;
    };

    modal.querySelectorAll('[data-numbering-confirmation-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    document.addEventListener('keydown', (event) => {
        if (modal.hidden || event.key !== 'Escape') return;
        event.preventDefault();
        close();
    });

    modal.querySelector('[data-numbering-confirmation-accept]')?.addEventListener('click', () => {
        if (!pendingForm) return;

        const form = pendingForm;
        const submitter = pendingSubmitter;
        form.dataset.numberingConfirmationApproved = 'true';
        form.dataset.confirmed = 'true';
        modal.hidden = true;
        document.body.classList.remove('overflow-hidden');
        form.requestSubmit(submitter || undefined);
        delete form.dataset.numberingConfirmationApproved;
    });

    return modal;
};

const openModal = (form, submitter) => {
    const modal = ensureModal();
    const copy = modalCopy(form);
    const oldNumber = findOldNumber(form);
    const reason = form.querySelector('[name="reason"]')?.value?.trim() || '';

    pendingForm = form;
    pendingSubmitter = submitter;
    pendingTrigger = submitter instanceof HTMLElement ? submitter : document.activeElement;

    modal.querySelector('[data-numbering-confirmation-kicker]').textContent = copy.kicker;
    modal.querySelector('[data-numbering-confirmation-title]').textContent = copy.title;
    modal.querySelector('[data-numbering-confirmation-message]').textContent = form.dataset.confirm || 'Lanjutkan penerbitan nomor baru?';
    modal.querySelector('[data-numbering-confirmation-note]').textContent = copy.note;
    modal.querySelector('[data-numbering-confirmation-accept]').textContent = copy.accept;

    const oldWrapper = modal.querySelector('[data-numbering-confirmation-old-wrapper]');
    const oldValue = modal.querySelector('[data-numbering-confirmation-old]');
    oldWrapper.hidden = !oldNumber;
    oldValue.textContent = oldNumber || '—';

    const reasonWrapper = modal.querySelector('[data-numbering-confirmation-reason-wrapper]');
    const reasonValue = modal.querySelector('[data-numbering-confirmation-reason]');
    reasonWrapper.hidden = !reason;
    reasonValue.textContent = reason || '—';

    modal.hidden = false;
    document.body.classList.add('overflow-hidden');
    window.requestAnimationFrame(() => modal.querySelector('[data-numbering-confirmation-close]')?.focus());
};

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches(NUMBERING_FORM_SELECTOR)) return;
    if (form.dataset.numberingConfirmationApproved === 'true') return;

    event.preventDefault();

    // Dialog global memakai flag yang sama. Menandainya di fase capture membuat
    // form penomoran tidak membuka dua modal sekaligus.
    form.dataset.confirmed = 'true';
    openModal(form, event.submitter);
}, true);
