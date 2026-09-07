const arrangeTransactionDetailCommonFields = () => {
    const form = document.querySelector('form[action*="/spj/"][action*="/siapkan"]');
    if (!(form instanceof HTMLFormElement)) return;

    const description = form.querySelector('textarea[name="payment_description"]');
    const paymentMethod = form.querySelector('[name="payment_method"]');
    const paymentReference = form.querySelector('[name="payment_reference"]');
    const receiptRecipient = form.querySelector('[name="receipt_recipient_name"]');

    if (!(description instanceof HTMLTextAreaElement)) return;
    if (!(paymentMethod instanceof HTMLElement)) return;
    if (!(paymentReference instanceof HTMLElement)) return;
    if (!(receiptRecipient instanceof HTMLElement)) return;

    const descriptionField = description.closest('div');
    const methodField = paymentMethod.closest('div');
    const referenceField = paymentReference.closest('div');
    const recipientField = receiptRecipient.closest('div');
    const commonGrid = descriptionField?.parentElement;

    if (!(descriptionField instanceof HTMLElement)
        || !(methodField instanceof HTMLElement)
        || !(referenceField instanceof HTMLElement)
        || !(recipientField instanceof HTMLElement)
        || !(commonGrid instanceof HTMLElement)) return;

    if (commonGrid.dataset.transactionCommonFieldsLayout === '1') {
        description.rows = 7;
        description.style.minHeight = '11rem';
        return;
    }

    commonGrid.dataset.transactionCommonFieldsLayout = '1';
    commonGrid.classList.remove('lg:grid-cols-4');
    commonGrid.classList.add('lg:grid-cols-2', 'gap-3');

    descriptionField.classList.remove('lg:col-span-2');
    descriptionField.classList.add('transaction-detail-description-field');
    description.rows = 7;
    description.style.minHeight = '11rem';
    description.style.resize = 'vertical';

    const sideGrid = document.createElement('div');
    sideGrid.className = 'transaction-detail-common-side-grid grid content-start gap-3 sm:grid-cols-2';
    sideGrid.dataset.transactionCommonSideGrid = '1';

    sideGrid.append(methodField, referenceField, recipientField);
    commonGrid.append(descriptionField, sideGrid);
};

const bootTransactionDetailCommonFields = () => {
    requestAnimationFrame(() => requestAnimationFrame(arrangeTransactionDetailCommonFields));
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootTransactionDetailCommonFields, { once: true });
} else {
    bootTransactionDetailCommonFields();
}

document.addEventListener('livewire:navigated', bootTransactionDetailCommonFields);
