const placePackageDocumentsInDetailsTab = () => {
    const workspace = document.querySelector('.spj-semantic-workspace');
    if (!workspace) return;

    const detailsPanel = workspace.querySelector('[data-panel="rincian"]');
    if (!detailsPanel) return;

    const documentSection = Array.from(workspace.querySelectorAll('section')).find((section) => {
        if (section.dataset.spjDocumentTemplates === 'true') return true;

        const heading = section.querySelector('h2');
        return heading?.textContent?.trim() === 'Dokumen & Template';
    });

    if (!documentSection || documentSection.parentElement === detailsPanel) return;

    documentSection.dataset.spjDocumentTemplates = 'true';
    detailsPanel.appendChild(documentSection);
};

const schedulePlacement = () => window.requestAnimationFrame(placePackageDocumentsInDetailsTab);

document.addEventListener('DOMContentLoaded', schedulePlacement);
document.addEventListener('livewire:navigated', schedulePlacement);

const observer = new MutationObserver((mutations) => {
    if (mutations.some((mutation) => mutation.addedNodes.length > 0)) {
        schedulePlacement();
    }
});

observer.observe(document.documentElement, { childList: true, subtree: true });
