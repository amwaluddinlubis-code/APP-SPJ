const validIsoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value || '');

const initializePurchaseDateValidation = (root = document) => {
    const forms = new Set();
    root.querySelectorAll?.('input[name="order_date"], input[name="bap_date"], input[name="bast_date"]').forEach((input) => {
        const form = input.closest('form');
        if (form) forms.add(form);
    });

    forms.forEach((form) => {
        if (form.dataset.spjPurchaseDateValidation === 'true') return;

        const order = form.querySelector('input[name="order_date"]');
        const bap = form.querySelector('input[name="bap_date"]');
        const bast = form.querySelector('input[name="bast_date"]');
        if (!(order instanceof HTMLInputElement) || !(bap instanceof HTMLInputElement) || !(bast instanceof HTMLInputElement)) return;

        form.dataset.spjPurchaseDateValidation = 'true';

        // Tanggal transaksi sudah tersedia sebagai batas statis di markup lama.
        // Pada detail transaksi, invoice_date menyimpan batas transaksi yang stabil;
        // pada tab Paket, order_date sendiri masih memiliki max tanggal transaksi.
        const invoice = form.querySelector('input[name="invoice_date"]');
        const transactionDate = validIsoDate(invoice?.getAttribute('max'))
            ? invoice.getAttribute('max')
            : (validIsoDate(order.getAttribute('max')) ? order.getAttribute('max') : null);

        const applyConstraints = () => {
            // 1. TGL PESANAN <= TGL TRANSAKSI
            // 2. TGL PESANAN <= TGL BAP
            const orderMaxCandidates = [transactionDate, bap.value].filter(validIsoDate).sort();
            if (orderMaxCandidates.length) order.max = orderMaxCandidates[0];
            else order.removeAttribute('max');
            order.removeAttribute('min');

            // 2. TGL PESANAN <= TGL BAP
            if (validIsoDate(order.value)) bap.min = order.value;
            else bap.removeAttribute('min');

            // 3. TGL BAP <= TGL BAST
            if (validIsoDate(bast.value)) bap.max = bast.value;
            else bap.removeAttribute('max');

            if (validIsoDate(bap.value)) bast.min = bap.value;
            else bast.removeAttribute('min');
            bast.removeAttribute('max');

            order.setCustomValidity('');
            bap.setCustomValidity('');
            bast.setCustomValidity('');

            if (validIsoDate(transactionDate) && validIsoDate(order.value) && order.value > transactionDate) {
                order.setCustomValidity('Tanggal Pesanan tidak boleh setelah Tanggal Transaksi.');
            } else if (validIsoDate(order.value) && validIsoDate(bap.value) && order.value > bap.value) {
                order.setCustomValidity('Tanggal Pesanan tidak boleh setelah Tanggal BAP.');
            }

            if (validIsoDate(order.value) && validIsoDate(bap.value) && bap.value < order.value) {
                bap.setCustomValidity('Tanggal BAP tidak boleh sebelum Tanggal Pesanan.');
            } else if (validIsoDate(bap.value) && validIsoDate(bast.value) && bap.value > bast.value) {
                bap.setCustomValidity('Tanggal BAP tidak boleh setelah Tanggal BAST.');
            }

            if (validIsoDate(bap.value) && validIsoDate(bast.value) && bast.value < bap.value) {
                bast.setCustomValidity('Tanggal BAST tidak boleh sebelum Tanggal BAP.');
            }
        };

        [order, bap, bast].forEach((input) => {
            input.addEventListener('input', () => requestAnimationFrame(applyConstraints));
            input.addEventListener('change', () => requestAnimationFrame(applyConstraints));
        });

        form.addEventListener('submit', (event) => {
            applyConstraints();
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });

        requestAnimationFrame(applyConstraints);
    });
};

const bootPurchaseDateValidation = () => initializePurchaseDateValidation(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPurchaseDateValidation, { once: true });
} else {
    bootPurchaseDateValidation();
}

document.addEventListener('livewire:navigated', bootPurchaseDateValidation);

new MutationObserver((mutations) => {
    if (mutations.some((mutation) => Array.from(mutation.addedNodes).some((node) => node.nodeType === Node.ELEMENT_NODE))) {
        initializePurchaseDateValidation(document);
    }
}).observe(document.documentElement, { childList: true, subtree: true });
