const actionSelector = 'button, a[href]';

const directCanonicalIcons = (action) => Array.from(action.children)
    .filter((child) => child instanceof SVGElement);

const directDetailIcons = (action) => Array.from(action.children)
    .filter((child) => child.classList?.contains('transaction-detail-inline-icon'));

const dedupeAction = (action) => {
    if (!(action instanceof HTMLElement) || !action.matches(actionSelector)) return;

    const canonicalIcons = directCanonicalIcons(action);
    const detailIcons = directDetailIcons(action);

    // Canonical x-ui/global icons win over icons injected by page-specific normalizers.
    if (canonicalIcons.length && detailIcons.length) {
        detailIcons.forEach((icon) => icon.remove());
        return;
    }

    // A page-specific normalizer should never own more than one icon per action.
    if (detailIcons.length > 1) {
        detailIcons.slice(1).forEach((icon) => icon.remove());
    }
};

const dedupeActionIcons = (root = document) => {
    const actions = [];

    if (root instanceof HTMLElement) {
        const parentAction = root.closest(actionSelector);
        if (parentAction) actions.push(parentAction);
        if (root.matches(actionSelector)) actions.push(root);
    }

    root.querySelectorAll?.(actionSelector).forEach((action) => actions.push(action));
    [...new Set(actions)].forEach(dedupeAction);
};

const boot = () => dedupeActionIcons(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

document.addEventListener('livewire:navigated', boot);

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof HTMLElement) dedupeActionIcons(node);
        });
    });
}).observe(document.body, { childList: true, subtree: true });

export { dedupeActionIcons };
