const normalizePreparationStates = (root = document) => {
    const scope = root.querySelector?.('.spj-semantic-workspace') || document.querySelector('.spj-semantic-workspace');
    if (!(scope instanceof HTMLElement)) return;

    scope.querySelectorAll('a[href*="state=needs_details"]').forEach((link) => {
        try {
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('state', 'attention');
            link.href = url.toString();
        } catch {
            return;
        }

        const label = link.querySelector('span');
        if (label) label.textContent = 'Perlu Perhatian';
    });

    scope.querySelectorAll('select[name="state"] option').forEach((option) => {
        if (option.value === 'needs_details') {
            option.value = 'attention';
            option.textContent = 'Perlu Perhatian';
        }
        if (option.value === 'ready') {
            option.textContent = 'Siap Dinomori';
        }
    });
};

normalizePreparationStates();
document.addEventListener('livewire:navigated', () => normalizePreparationStates());
