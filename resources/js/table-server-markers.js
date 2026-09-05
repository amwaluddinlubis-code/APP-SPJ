const pageSizeOptions = [10, 15, 25, 50, 100];

const tableConfig = (table) => {
    if (table.classList.contains('reconciliation-table')) {
        return { page: 'reconciliation_page', perPage: 'reconciliation_perPage' };
    }
    if (table.classList.contains('register-table')) {
        return { page: 'register_page', perPage: 'register_perPage' };
    }
    if (table.classList.contains('completeness-table')) {
        return { page: 'completeness_page', perPage: 'completeness_perPage' };
    }
    if (table.classList.contains('history-table')) {
        return { page: 'history_page', perPage: 'history_perPage' };
    }

    const tabPanel = table.closest('[x-show]');
    const tabExpression = tabPanel?.getAttribute('x-show') || '';
    const url = new URL(window.location.href);
    if (
        window.location.pathname === '/spj'
        && tabExpression.includes('paket')
        && (url.searchParams.get('tab') || 'persiapan') === 'paket'
        && !url.searchParams.has('package_id')
    ) {
        return { page: 'package_page', perPage: 'package_perPage' };
    }

    return null;
};

const headingTarget = (table) => {
    const container = table.parentElement?.parentElement;
    if (!(container instanceof HTMLElement)) return null;

    return Array.from(container.children).find((child) => child.matches?.('.audit-panel-heading, .ui-toolbar, div.border-b')) || null;
};

const addPageSizeControl = (table, config) => {
    if (table.dataset.serverPaginationUiInitialized === 'true') return;

    table.dataset.pagination = 'server';
    table.dataset.serverPaginationUiInitialized = 'true';

    const target = headingTarget(table);
    if (!(target instanceof HTMLElement)) return;
    if (target.querySelector(`[data-server-page-size="${config.perPage}"]`)) return;

    const url = new URL(window.location.href);
    let current = Number(url.searchParams.get(config.perPage) || 15);
    if (!pageSizeOptions.includes(current)) current = 15;

    const control = document.createElement('div');
    control.dataset.serverPageSize = config.perPage;
    control.className = 'ml-auto flex items-center gap-2 text-xs';

    const label = document.createElement('label');
    label.className = 'font-semibold';
    label.style.color = 'var(--ui-fg-muted)';
    label.textContent = 'Baris';

    const select = document.createElement('select');
    select.className = 'ui-select !min-h-9 !w-auto !py-1.5 !text-xs';
    select.setAttribute('aria-label', 'Baris per halaman');

    pageSizeOptions.forEach((size) => {
        const option = document.createElement('option');
        option.value = String(size);
        option.textContent = `${size} baris`;
        option.selected = size === current;
        select.appendChild(option);
    });

    select.addEventListener('change', () => {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set(config.perPage, select.value);
        nextUrl.searchParams.delete(config.page);
        window.location.assign(nextUrl.toString());
    });

    control.append(label, select);
    target.classList.add('flex', 'items-center', 'gap-2', 'flex-wrap');
    target.appendChild(control);
};

const markKnownServerTables = (root = document) => {
    const tables = root.querySelectorAll?.('table') || [];
    tables.forEach((table) => {
        const config = tableConfig(table);
        if (config) addPageSizeControl(table, config);
    });
};

const ready = () => markKnownServerTables(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready, { once: true });
} else {
    ready();
}

document.addEventListener('livewire:navigated', ready);

export { markKnownServerTables };
