const initializeTransactionCategoryControls = (root = document) => {
    const categorySelect = root.querySelector?.('#detail-spj-type') || document.querySelector('#detail-spj-type');
    if (!(categorySelect instanceof HTMLSelectElement)) return;

    const form = categorySelect.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    const serviceSection = Array.from(form.querySelectorAll('section')).find((section) =>
        section.getAttribute('x-show')?.includes("category === 'JASA_LAINNYA'"),
    );

    if (!(serviceSection instanceof HTMLElement)) return;
    if (serviceSection.dataset.categoryControlsInitialized === 'true') return;

    const synchronize = () => {
        const isServiceCategory = categorySelect.value.toUpperCase() === 'JASA_LAINNYA';

        serviceSection.querySelectorAll('input, select, textarea').forEach((control) => {
            if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement) {
                control.disabled = !isServiceCategory;
            }
        });
    };

    categorySelect.addEventListener('change', synchronize);
    serviceSection.dataset.categoryControlsInitialized = 'true';
    synchronize();
};

initializeTransactionCategoryControls();
document.addEventListener('alpine:initialized', () => initializeTransactionCategoryControls());
document.addEventListener('livewire:navigated', () => initializeTransactionCategoryControls());
