const alignTransactionSummaryCards = (root = document) => {
    const scope = root instanceof Document || root instanceof HTMLElement ? root : document;
    const fillDataLink = scope.querySelector?.('a[href="#modul-buat-spj"]') || document.querySelector('a[href="#modul-buat-spj"]');

    if (!(fillDataLink instanceof HTMLAnchorElement)) return;

    const checklistPanel = fillDataLink.closest('.ui-panel');
    if (!(checklistPanel instanceof HTMLElement)) return;

    const headings = Array.from(document.querySelectorAll('h2'));
    const taxHeading = headings.find((heading) => heading.textContent?.trim() === 'Rincian Pajak');
    const documentHeading = headings.find((heading) => heading.textContent?.trim() === 'Informasi Dokumen SPJ');

    const taxCard = taxHeading?.closest('article');
    const documentCard = documentHeading?.closest('article');
    const summaryGrid = taxCard?.parentElement;

    if (!(taxCard instanceof HTMLElement)
        || !(documentCard instanceof HTMLElement)
        || !(summaryGrid instanceof HTMLElement)
        || documentCard.parentElement !== summaryGrid) {
        return;
    }

    if (checklistPanel.parentElement !== summaryGrid) {
        const previousSection = checklistPanel.parentElement;
        summaryGrid.insertBefore(checklistPanel, taxCard);

        if (previousSection instanceof HTMLElement && previousSection.children.length === 1) {
            previousSection.classList.remove('lg:grid-cols-[minmax(0,.75fr)_minmax(0,1.25fr)]');
            previousSection.classList.add('lg:grid-cols-1');
        }
    }

    summaryGrid.classList.remove('lg:grid-cols-2');
    summaryGrid.classList.add('md:grid-cols-2', 'xl:grid-cols-3', 'items-stretch');

    [checklistPanel, taxCard, documentCard].forEach((card) => {
        card.classList.add('h-full');
    });
};

const initializeTransactionSummaryCards = () => alignTransactionSummaryCards(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTransactionSummaryCards, { once: true });
} else {
    initializeTransactionSummaryCards();
}

document.addEventListener('livewire:navigated', initializeTransactionSummaryCards);
