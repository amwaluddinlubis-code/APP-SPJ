const pageSizeOptions = [10, 15, 25, 50, 100];

const buttonClass = 'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40';

const applyButtonTheme = (button, active = false) => {
    if (!(button instanceof HTMLButtonElement)) return;

    button.className = buttonClass;
    if (active) {
        button.style.borderColor = 'var(--theme-content-accent)';
        button.style.background = 'var(--theme-content-accent)';
        button.style.color = '#fff';
        return;
    }

    button.style.borderColor = 'var(--ui-line)';
    button.style.background = 'var(--ui-bg)';
    button.style.color = 'var(--ui-fg-muted)';
};

const toolbarTargetFor = (table) => {
    const section = table.closest('section, .card, .audit-panel');
    if (!(section instanceof HTMLElement)) return null;

    const toolbar = section.querySelector('.ui-toolbar');
    if (toolbar instanceof HTMLElement) {
        const groups = toolbar.querySelectorAll(':scope > .ui-toolbar-group');
        return groups.length > 1 ? groups[groups.length - 1] : toolbar;
    }

    const heading = section.querySelector(':scope > .audit-panel-heading, :scope > header, :scope > div.border-b');
    return heading instanceof HTMLElement ? heading : null;
};

const createPageSizeControl = (table, perPage, onChange) => {
    const control = document.createElement('div');
    control.dataset.clientPageSize = 'true';
    control.className = 'flex items-center gap-2 text-xs';

    const label = document.createElement('label');
    label.className = 'font-semibold';
    label.style.color = 'var(--ui-fg-muted)';
    label.textContent = 'Baris';

    const select = document.createElement('select');
    select.setAttribute('aria-label', 'Baris per halaman');
    select.className = 'ui-select !min-h-9 !w-auto !py-1.5 !text-xs';

    pageSizeOptions.forEach((size) => {
        const option = document.createElement('option');
        option.value = String(size);
        option.textContent = `${size} baris`;
        select.appendChild(option);
    });

    if (!pageSizeOptions.includes(perPage)) {
        perPage = pageSizeOptions[0];
    }
    select.value = String(perPage);
    select.addEventListener('change', () => onChange(Number(select.value)));

    control.append(label, select);

    const target = toolbarTargetFor(table);
    if (target) {
        target.classList.add('flex', 'items-center', 'gap-2');
        target.appendChild(control);
        return control;
    }

    const fallbackToolbar = document.createElement('div');
    fallbackToolbar.dataset.generatedTableToolbar = 'true';
    fallbackToolbar.className = 'ui-toolbar border-b px-4 py-2.5';
    fallbackToolbar.style.borderColor = 'var(--ui-line)';
    fallbackToolbar.style.background = 'var(--ui-bg-subtle)';
    fallbackToolbar.appendChild(control);
    table.parentElement?.insertAdjacentElement('beforebegin', fallbackToolbar);

    return control;
};

const serverPaginationNearby = (table) => {
    const section = table.closest('section, .card, .audit-panel');
    if (!(section instanceof HTMLElement)) return false;

    return Boolean(section.querySelector('nav[role="navigation"]'));
};

const initializeStandardClientTable = (table) => {
    if (!(table instanceof HTMLTableElement)) return;
    if (table.dataset.paginationInitialized === 'true') return;
    if (table.dataset.pagination === 'none' || table.dataset.pagination === 'server') return;
    if (table.closest('[wire\\:id]')) return;

    if (serverPaginationNearby(table)) {
        table.dataset.pagination = 'server';
        return;
    }

    const body = table.tBodies[0];
    if (!body) return;

    const rows = Array.from(body.rows);
    if (rows.length <= 10) return;

    table.dataset.paginationInitialized = 'true';

    let page = 1;
    let perPage = Number(table.dataset.perPage || 10);
    if (!pageSizeOptions.includes(perPage)) perPage = 10;

    const pagination = document.createElement('div');
    pagination.className = 'app-table-pagination';
    pagination.style.borderColor = 'var(--ui-line)';
    pagination.style.background = 'var(--ui-bg-subtle)';

    const summary = document.createElement('div');
    summary.className = 'text-xs';
    summary.style.color = 'var(--ui-fg-muted)';

    const nav = document.createElement('div');
    nav.className = 'flex items-center gap-1';
    nav.setAttribute('aria-label', 'Navigasi halaman tabel');

    pagination.append(summary, nav);
    table.parentElement?.insertAdjacentElement('afterend', pagination);

    const render = () => {
        const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
        page = Math.min(Math.max(1, page), totalPages);
        const start = (page - 1) * perPage;
        const end = Math.min(start + perPage, rows.length);

        rows.forEach((row, index) => {
            row.hidden = index < start || index >= end;
        });

        summary.innerHTML = `Menampilkan <strong>${rows.length ? start + 1 : 0}–${end}</strong> dari <strong>${rows.length}</strong> baris`;
        nav.replaceChildren();

        const previous = document.createElement('button');
        previous.type = 'button';
        previous.textContent = '‹';
        previous.title = 'Halaman sebelumnya';
        previous.disabled = page <= 1;
        applyButtonTheme(previous);
        previous.addEventListener('click', () => {
            page -= 1;
            render();
        });
        nav.appendChild(previous);

        const firstPage = Math.max(1, page - 2);
        const lastPage = Math.min(totalPages, page + 2);
        for (let number = firstPage; number <= lastPage; number += 1) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(number);
            button.setAttribute('aria-current', number === page ? 'page' : 'false');
            applyButtonTheme(button, number === page);
            button.addEventListener('click', () => {
                page = number;
                render();
            });
            nav.appendChild(button);
        }

        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = '›';
        next.title = 'Halaman berikutnya';
        next.disabled = page >= totalPages;
        applyButtonTheme(next);
        next.addEventListener('click', () => {
            page += 1;
            render();
        });
        nav.appendChild(next);
    };

    createPageSizeControl(table, perPage, (value) => {
        perPage = value;
        page = 1;
        render();
    });

    render();
};

const initializeStandardTables = (root = document) => {
    const tables = [];
    if (root instanceof HTMLTableElement) tables.push(root);
    if (root.querySelectorAll) tables.push(...root.querySelectorAll('table'));
    tables.forEach(initializeStandardClientTable);
};

const ready = () => initializeStandardTables(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready, { once: true });
} else {
    ready();
}

document.addEventListener('livewire:navigated', () => initializeStandardTables(document));

export { initializeStandardTables };
