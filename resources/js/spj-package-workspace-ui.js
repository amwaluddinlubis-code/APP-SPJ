const notify = (type, message) => {
    window.dispatchEvent(new CustomEvent('app-notify', { detail: { type, message } }));
};

const initializePackageNavigation = () => {
    const navigation = document.querySelector('[data-package-navigation]');
    if (!(navigation instanceof HTMLElement) || navigation.dataset.packageNavigationBound === 'true') return;

    navigation.dataset.packageNavigationBound = 'true';

    const legacyToolbar = navigation.previousElementSibling;
    if (legacyToolbar instanceof HTMLElement) {
        const text = legacyToolbar.textContent || '';
        if (text.includes('Semua paket') && text.includes('Lihat transaksi')) {
            legacyToolbar.remove();
        }
    }

    navigation.hidden = false;

    navigation.querySelectorAll('[data-package-nav-missing]').forEach((button) => {
        button.addEventListener('click', () => {
            const direction = button.dataset.packageNavMissing === 'previous' ? 'sebelumnya' : 'setelahnya';
            notify('warning', `Tidak ada Paket SPJ ${direction} pada Tahun Anggaran dan Sumber Dana aktif.`);
        });
    });
};

const humanNumberLabel = (key) => ({
    order: 'Pesanan',
    pesanan: 'Pesanan',
    bap: 'BAP',
    bast: 'BAST',
    spk: 'SPK',
    rab: 'RAB',
    assignmentLetter: 'Surat Tugas',
}[key] || key.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/^./, (char) => char.toUpperCase()));

const captureLegacyAutomaticNumbers = (form) => {
    form.querySelectorAll('fieldset[data-spj-section]').forEach((section) => {
        if (!(section instanceof HTMLElement)) return;

        section.querySelectorAll('input[readonly][name$="_number"]').forEach((input) => {
            if (!(input instanceof HTMLInputElement)) return;
            const rawKey = input.name.replace(/_number$/, '');
            const camelKey = rawKey.replace(/_([a-z])/g, (_, char) => char.toUpperCase());
            const datasetKey = `autoNumber${camelKey.charAt(0).toUpperCase()}${camelKey.slice(1)}`;
            if (!section.dataset[datasetKey]) section.dataset[datasetKey] = input.value || '';

            const wrapper = input.closest('div');
            if (wrapper instanceof HTMLElement) wrapper.remove();
        });
    });
};

const appendNumberInfo = (strip, label, value) => {
    const item = document.createElement('div');
    item.className = 'min-w-[9rem] flex-1 rounded border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2.5 py-1.5';

    const heading = document.createElement('p');
    heading.className = 'text-[10px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]';
    heading.textContent = label;

    const number = document.createElement('p');
    number.className = 'mt-0.5 truncate font-mono text-xs font-bold text-[var(--ui-fg-strong)]';
    number.textContent = value || 'Belum diterbitkan';
    number.title = value || 'Belum diterbitkan';

    item.append(heading, number);
    strip.appendChild(item);
};

const initializeAutomaticNumberStrip = () => {
    const form = document.querySelector('#spj-manual-form');
    const categorySelect = form?.querySelector('#spj-type');
    if (!(form instanceof HTMLFormElement) || !(categorySelect instanceof HTMLSelectElement)) return;

    captureLegacyAutomaticNumbers(form);

    let strip = form.querySelector('[data-spj-auto-number-strip]');
    if (!(strip instanceof HTMLElement)) {
        const categoryGrid = categorySelect.parentElement?.parentElement;
        const categoryPanel = categoryGrid?.parentElement;
        if (!(categoryPanel instanceof HTMLElement)) return;

        strip = document.createElement('div');
        strip.dataset.spjAutoNumberStrip = 'true';
        strip.className = 'mt-2 flex flex-wrap items-stretch gap-2 rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-2';
        categoryPanel.insertAdjacentElement('afterend', strip);
    }

    const render = () => {
        const category = String(categorySelect.value || '').toUpperCase();
        const summary = document.querySelector('[data-spj-package-summary]');
        const entries = [['SPJ', summary?.dataset.spjMainNumber || '']];
        const section = Array.from(form.querySelectorAll('fieldset[data-spj-section]')).find((candidate) =>
            String(candidate.dataset.spjSection || '').split(/\s+/).includes(category),
        );

        if (section instanceof HTMLElement) {
            Object.entries(section.dataset).forEach(([key, value]) => {
                if (!key.startsWith('autoNumber') || key === 'autoNumber') return;
                const name = key.slice('autoNumber'.length);
                const normalized = name.charAt(0).toLowerCase() + name.slice(1);
                entries.push([humanNumberLabel(normalized), value || '']);
            });
        }

        strip.replaceChildren();
        entries.forEach(([label, value]) => appendNumberInfo(strip, label, value));
    };

    if (strip.dataset.spjAutoNumberBound !== 'true') {
        strip.dataset.spjAutoNumberBound = 'true';
        categorySelect.addEventListener('change', render);
        document.addEventListener('spj:category-ui-updated', render);
        document.addEventListener('spj:category-changed', render);
    }

    render();
};

const initializeSpjPackageWorkspaceUi = () => {
    initializePackageNavigation();
    window.requestAnimationFrame(initializeAutomaticNumberStrip);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSpjPackageWorkspaceUi, { once: true });
} else {
    initializeSpjPackageWorkspaceUi();
}

document.addEventListener('livewire:navigated', initializeSpjPackageWorkspaceUi);
document.addEventListener('spj:category-context-ready', () => window.requestAnimationFrame(initializeAutomaticNumberStrip));
