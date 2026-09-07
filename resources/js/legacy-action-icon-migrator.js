const actionIconRules = [
    { pattern: /^(simpan|simpan\b)/i, icon: 'save' },
    { pattern: /^(buat paket spj|buat paket)/i, icon: 'plus' },
    { pattern: /^(tambah|tambahkan|\+\s*)/i, icon: 'plus' },
    { pattern: /^(hapus|delete|buang)/i, icon: 'trash' },
    { pattern: /^(edit|ubah|perbaiki)/i, icon: 'edit' },
    { pattern: /^(unduh|download)/i, icon: 'download' },
    { pattern: /^(cetak|print|pratinjau.*pdf|preview.*pdf)/i, icon: 'printer' },
    { pattern: /^(kembali|semua paket)/i, icon: 'arrow-left' },
    { pattern: /^(refresh|muat ulang|reload|sinkronisasi|sinkronkan)/i, icon: 'refresh' },
    { pattern: /^(naikkan urutan|naik)$/i, icon: 'arrow-up' },
    { pattern: /^(turunkan urutan|turun)$/i, icon: 'arrow-down' },
    { pattern: /^(lihat transaksi|buka transaksi|lihat detail|detail transaksi|lihat paket)/i, icon: 'eye' },
    { pattern: /^(buka paket|buka paket spj)/i, icon: 'external-link' },
    { pattern: /^(isi data)/i, icon: 'edit' },
    { pattern: /^(paket terkunci)/i, icon: 'lock' },
    { pattern: /^(rincian)(\b|$)/i, icon: 'document' },
    { pattern: /^(isian manual)(\b|$)/i, icon: 'edit' },
    { pattern: /^(kesiapan)(\b|$)/i, icon: 'check' },
    { pattern: /^(penomoran)(\b|$)/i, icon: 'document' },
    { pattern: /^(berikutnya|lanjut)/i, icon: 'arrow-right' },
];

const legacyPrefixPattern = /^[\s\u2190-\u21ff\u2600-\u27bf\u{1f300}-\u{1faff}]+/u;

const cleanActionLabel = (value) => (value || '')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(legacyPrefixPattern, '')
    .replace(/[→›»]+\s*$/, '')
    .trim();

const normalizeActionLabel = (element) => {
    const textLabel = cleanActionLabel(element.textContent);
    if (textLabel) return textLabel;

    return cleanActionLabel(element.getAttribute('aria-label') || element.getAttribute('title'));
};

const resolveIconName = (label) => {
    const rule = actionIconRules.find(({ pattern }) => pattern.test(label));
    return rule?.icon || null;
};

const iconFromTemplate = (name) => {
    const template = document.querySelector(`[data-ui-icon-template="${name}"]`);
    if (!(template instanceof HTMLTemplateElement)) return null;

    const icon = template.content.firstElementChild?.cloneNode(true);
    return icon instanceof SVGElement ? icon : null;
};

const migrateAction = (element) => {
    if (!(element instanceof HTMLElement)) return;
    if (element.dataset.uiIconMigrated === 'true') return;
    if (element.closest('[data-ui-icon-templates]')) return;
    if (element.querySelector(':scope > svg')) {
        element.dataset.uiIconMigrated = 'true';
        return;
    }

    const label = normalizeActionLabel(element);
    if (!label) return;

    const iconName = resolveIconName(label);
    if (!iconName) return;

    const icon = iconFromTemplate(iconName);
    if (!icon) return;

    const rawText = element.textContent?.replace(/\s+/g, ' ').trim() || '';
    const cleanedText = cleanActionLabel(rawText);
    if (rawText && rawText !== cleanedText && element.childElementCount === 0) {
        element.textContent = cleanedText;
    } else if (/^[\s\u2190-\u21ff\u2600-\u27bf\u{1f300}-\u{1faff}]+/u.test(rawText)) {
        const firstTextNode = Array.from(element.childNodes).find((node) => node.nodeType === Node.TEXT_NODE && node.nodeValue?.trim());
        if (firstTextNode) firstTextNode.nodeValue = firstTextNode.nodeValue.replace(legacyPrefixPattern, '');
    }

    element.classList.add('inline-flex', 'items-center', 'gap-1.5');
    element.prepend(icon);
    element.dataset.uiIconMigrated = 'true';
};

const migrateLegacyActionIcons = (root = document) => {
    const scope = root instanceof Document || root instanceof HTMLElement ? root : document;
    const candidates = [];

    if (scope instanceof HTMLElement && scope.matches('button, a[href]')) candidates.push(scope);
    scope.querySelectorAll?.('button, a[href]').forEach((element) => candidates.push(element));

    candidates.forEach(migrateAction);
};

const initializeLegacyActionIcons = () => migrateLegacyActionIcons(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLegacyActionIcons, { once: true });
} else {
    initializeLegacyActionIcons();
}

document.addEventListener('livewire:navigated', initializeLegacyActionIcons);

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) migrateLegacyActionIcons(node);
        });
    });
}).observe(document.body, { childList: true, subtree: true });
