const findHeading = (text) => Array.from(document.querySelectorAll('h2'))
    .find((heading) => heading.textContent?.trim() === text);

const alignTransactionSummaryCards = () => {
    const checklistHeading = findHeading('Yang perlu dilengkapi');
    const taxHeading = findHeading('Rincian Pajak');
    const documentHeading = findHeading('Informasi Dokumen SPJ');

    const checklistPanel = checklistHeading?.closest('.ui-panel');
    const taxCard = taxHeading?.closest('article');
    const documentCard = documentHeading?.closest('article');
    const summaryGrid = taxCard?.parentElement;

    if (!(checklistPanel instanceof HTMLElement)
        || !(taxCard instanceof HTMLElement)
        || !(documentCard instanceof HTMLElement)
        || !(summaryGrid instanceof HTMLElement)
        || documentCard.parentElement !== summaryGrid) {
        return;
    }

    if (checklistPanel.parentElement !== summaryGrid) {
        const previousSection = checklistPanel.parentElement;
        documentCard.insertAdjacentElement('afterend', checklistPanel);

        if (previousSection instanceof HTMLElement && previousSection.children.length === 1) {
            previousSection.classList.remove('lg:grid-cols-[minmax(0,.75fr)_minmax(0,1.25fr)]');
            previousSection.classList.add('lg:grid-cols-1');
        }
    } else if (checklistPanel.previousElementSibling !== documentCard) {
        documentCard.insertAdjacentElement('afterend', checklistPanel);
    }

    summaryGrid.classList.remove('lg:grid-cols-2');
    summaryGrid.classList.add('md:grid-cols-2', 'xl:grid-cols-3', 'items-stretch');

    [taxCard, documentCard, checklistPanel].forEach((card) => {
        card.classList.add('h-full');
    });
};

const initializeTransactionSummaryCards = () => alignTransactionSummaryCards();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTransactionSummaryCards, { once: true });
} else {
    initializeTransactionSummaryCards();
}

document.addEventListener('livewire:navigated', initializeTransactionSummaryCards);
