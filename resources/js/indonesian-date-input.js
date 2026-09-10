const ISO_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;
const DISPLAY_DATE_PATTERN = /^(\d{2})\/(\d{2})\/(\d{4})$/;
const VALIDATE_CALLBACK = Symbol('indonesianDateValidate');
const SYNC_CALLBACK = Symbol('indonesianDateSync');

const pad = (value) => String(value).padStart(2, '0');

export const formatIsoDateForDisplay = (value) => {
    const match = ISO_DATE_PATTERN.exec(value || '');
    if (!match) return '';

    const [, year, month, day] = match;
    return `${day}/${month}/${year}`;
};

export const parseIndonesianDate = (value) => {
    const match = DISPLAY_DATE_PATTERN.exec((value || '').trim());
    if (!match) return null;

    const [, dayText, monthText, yearText] = match;
    const day = Number(dayText);
    const month = Number(monthText);
    const year = Number(yearText);
    const candidate = new Date(year, month - 1, day);

    if (
        candidate.getFullYear() !== year
        || candidate.getMonth() !== month - 1
        || candidate.getDate() !== day
    ) {
        return null;
    }

    return `${yearText}-${pad(month)}-${pad(day)}`;
};

const visuallyHideNativeDateInput = (input) => {
    input.tabIndex = -1;
    input.setAttribute('aria-hidden', 'true');
    Object.assign(input.style, {
        position: 'absolute',
        width: '1px',
        height: '1px',
        padding: '0',
        margin: '-1px',
        overflow: 'hidden',
        clip: 'rect(0, 0, 0, 0)',
        whiteSpace: 'nowrap',
        border: '0',
        opacity: '0',
    });
};

const buildCalendarButton = (nativeInput) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.indonesianDatePicker = 'true';
    button.setAttribute('aria-label', 'Pilih tanggal');
    button.title = 'Pilih tanggal';
    button.innerHTML = `
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
        </svg>
    `;
    Object.assign(button.style, {
        position: 'absolute',
        right: '0.35rem',
        top: '50%',
        transform: 'translateY(-50%)',
        zIndex: '1',
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '2rem',
        height: '2rem',
        padding: '0',
        border: '0',
        borderRadius: '0.375rem',
        background: 'transparent',
        color: 'inherit',
        cursor: 'pointer',
    });

    button.addEventListener('click', () => {
        if (nativeInput.disabled || nativeInput.readOnly) return;

        nativeInput.focus({ preventScroll: true });
        if (typeof nativeInput.showPicker === 'function') {
            nativeInput.showPicker();
            return;
        }

        nativeInput.click();
    });

    return button;
};

const initializeDateInput = (nativeInput) => {
    if (!(nativeInput instanceof HTMLInputElement)) return;
    if (!nativeInput.matches('input[type="date"]')) return;
    if (nativeInput.dataset.indonesianDateInitialized === 'true') return;
    if (nativeInput.dataset.dateFormat === 'native') return;

    nativeInput.dataset.indonesianDateInitialized = 'true';
    nativeInput.lang = 'id-ID';

    const wrapper = document.createElement('span');
    wrapper.dataset.indonesianDateWrapper = 'true';
    Object.assign(wrapper.style, {
        position: 'relative',
        display: 'block',
        width: '100%',
        minWidth: '0',
    });

    const displayInput = document.createElement('input');
    displayInput.type = 'text';
    displayInput.className = nativeInput.className;
    displayInput.placeholder = 'dd/mm/yyyy';
    displayInput.inputMode = 'numeric';
    displayInput.autocomplete = 'off';
    displayInput.dataset.indonesianDateDisplay = 'true';
    displayInput.value = formatIsoDateForDisplay(nativeInput.value);
    displayInput.disabled = nativeInput.disabled;
    displayInput.readOnly = nativeInput.readOnly;
    displayInput.required = nativeInput.required;

    const sourceLabel = nativeInput.getAttribute('aria-label');
    if (sourceLabel) {
        displayInput.setAttribute('aria-label', `${sourceLabel} (dd/mm/yyyy)`);
    } else {
        displayInput.setAttribute('aria-label', 'Tanggal (dd/mm/yyyy)');
    }

    const describedBy = nativeInput.getAttribute('aria-describedby');
    if (describedBy) displayInput.setAttribute('aria-describedby', describedBy);

    const labelledBy = nativeInput.getAttribute('aria-labelledby');
    if (labelledBy) displayInput.setAttribute('aria-labelledby', labelledBy);

    Object.assign(displayInput.style, {
        width: '100%',
        minWidth: '0',
        paddingRight: '2.75rem',
    });

    const calendarButton = buildCalendarButton(nativeInput);
    calendarButton.disabled = nativeInput.disabled || nativeInput.readOnly;

    nativeInput.parentNode?.insertBefore(wrapper, nativeInput);
    wrapper.append(displayInput, calendarButton, nativeInput);
    visuallyHideNativeDateInput(nativeInput);

    let syncing = false;

    const syncDisplayFromNative = () => {
        if (syncing) return;
        displayInput.value = formatIsoDateForDisplay(nativeInput.value);
        displayInput.disabled = nativeInput.disabled;
        displayInput.readOnly = nativeInput.readOnly;
        displayInput.required = nativeInput.required;
        calendarButton.disabled = nativeInput.disabled || nativeInput.readOnly;
        displayInput.setCustomValidity('');
    };

    const setNativeValue = (isoValue) => {
        if (nativeInput.value === isoValue) return;

        syncing = true;
        nativeInput.value = isoValue;
        nativeInput.dispatchEvent(new Event('input', { bubbles: true }));
        nativeInput.dispatchEvent(new Event('change', { bubbles: true }));
        syncing = false;
    };

    const validateDisplay = ({ final = false } = {}) => {
        const value = displayInput.value.trim();
        displayInput.setCustomValidity('');

        if (!value) {
            setNativeValue('');
            return !displayInput.required;
        }

        const isoValue = parseIndonesianDate(value);
        if (!isoValue) {
            if (final || value.length >= 10) {
                displayInput.setCustomValidity('Gunakan tanggal yang valid dengan format dd/mm/yyyy.');
            }
            return false;
        }

        if (nativeInput.min && isoValue < nativeInput.min) {
            displayInput.setCustomValidity(`Tanggal tidak boleh sebelum ${formatIsoDateForDisplay(nativeInput.min)}.`);
            return false;
        }

        if (nativeInput.max && isoValue > nativeInput.max) {
            displayInput.setCustomValidity(`Tanggal tidak boleh setelah ${formatIsoDateForDisplay(nativeInput.max)}.`);
            return false;
        }

        setNativeValue(isoValue);
        return true;
    };

    const mirrorNativeValidation = () => {
        let message = nativeInput.validationMessage || '';

        if (!message && nativeInput.validity.rangeUnderflow && nativeInput.min) {
            message = `Tanggal tidak boleh sebelum ${formatIsoDateForDisplay(nativeInput.min)}.`;
        } else if (!message && nativeInput.validity.rangeOverflow && nativeInput.max) {
            message = `Tanggal tidak boleh setelah ${formatIsoDateForDisplay(nativeInput.max)}.`;
        } else if (!message && nativeInput.validity.valueMissing) {
            message = 'Tanggal wajib diisi.';
        } else if (!message && !nativeInput.validity.valid) {
            message = 'Tanggal tidak valid.';
        }

        if (!message) return false;

        displayInput.setCustomValidity(message);
        displayInput.focus({ preventScroll: true });
        displayInput.reportValidity();
        return true;
    };

    displayInput[VALIDATE_CALLBACK] = validateDisplay;
    nativeInput[SYNC_CALLBACK] = syncDisplayFromNative;

    displayInput.addEventListener('input', () => validateDisplay());
    displayInput.addEventListener('change', () => validateDisplay({ final: true }));
    displayInput.addEventListener('blur', () => validateDisplay({ final: true }));

    nativeInput.addEventListener('input', () => requestAnimationFrame(syncDisplayFromNative));
    nativeInput.addEventListener('change', () => requestAnimationFrame(syncDisplayFromNative));
    nativeInput.addEventListener('invalid', (event) => {
        event.preventDefault();
        mirrorNativeValidation();
    });

    const nativeStateObserver = new MutationObserver(() => {
        syncDisplayFromNative();
        validateDisplay();
    });
    nativeStateObserver.observe(nativeInput, {
        attributes: true,
        attributeFilter: ['min', 'max', 'disabled', 'readonly', 'required'],
    });
};

export const initializeIndonesianDateInputs = (root = document) => {
    if (root instanceof HTMLInputElement && root.matches('input[type="date"]')) {
        initializeDateInput(root);
    }

    root.querySelectorAll?.('input[type="date"]').forEach(initializeDateInput);
};

const bootIndonesianDateInputs = () => initializeIndonesianDateInputs(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootIndonesianDateInputs, { once: true });
} else {
    bootIndonesianDateInputs();
}

document.addEventListener('livewire:navigated', bootIndonesianDateInputs);

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    for (const displayInput of form.querySelectorAll('input[data-indonesian-date-display="true"]')) {
        const validate = displayInput[VALIDATE_CALLBACK];
        if (typeof validate === 'function' && !validate({ final: true })) {
            event.preventDefault();
            displayInput.focus({ preventScroll: true });
            displayInput.reportValidity();
            break;
        }
    }
}, true);

document.addEventListener('reset', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    requestAnimationFrame(() => {
        form.querySelectorAll('input[type="date"][data-indonesian-date-initialized="true"]').forEach((nativeInput) => {
            nativeInput[SYNC_CALLBACK]?.();
        });
    });
}, true);

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.type === 'attributes') {
            initializeIndonesianDateInputs(mutation.target);
            return;
        }

        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) initializeIndonesianDateInputs(node);
        });
    });
}).observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['type'],
});

window.AppDateInput = Object.freeze({
    formatIsoDateForDisplay,
    parseIndonesianDate,
    initialize: initializeIndonesianDateInputs,
});
