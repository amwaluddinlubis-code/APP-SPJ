@props([
    'total' => null,
    'name' => 'perPage',
    'current' => null,
    'options' => [15, 25, 50, 100],
    'allowAll' => false,
    'label' => 'Baris',
])

@php($selected = (string) ($current ?? request($name, '15')))

<div data-page-table-per-page {{ $attributes->class(['ui-toolbar-group flex items-center gap-2 text-xs']) }}>
    <label class="font-semibold" style="color: var(--ui-fg-muted)">{{ $label }}</label>
    <select
        aria-label="{{ $label }} per halaman"
        onchange="const u=new URL(window.location); u.searchParams.set('{{ $name }}', this.value); u.searchParams.delete('page'); window.location=u.toString()"
        class="ui-select !min-h-9 !w-auto !py-1.5 !text-xs"
    >
        @foreach($options as $opt)
            <option value="{{ $opt }}" @selected($selected === (string) $opt)>{{ $opt }} baris</option>
        @endforeach
        @if($allowAll)
            <option value="all" @selected($selected === 'all')>Semua</option>
        @endif
    </select>
    @if($total !== null)
        <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">• {{ number_format($total, 0, ',', '.') }} data</span>
    @endif
</div>

<style>
    @media (min-width: 1280px) {
        .spj-semantic-workspace form[data-spj-preparation-toolbar] {
            display: flex;
            align-items: flex-end;
            gap: .75rem;
            overflow-x: auto;
        }

        .spj-semantic-workspace form[data-spj-preparation-toolbar] > [data-spj-filter-grid] {
            display: grid;
            grid-template-columns: repeat(4, minmax(9.5rem, 1fr));
            flex: 1 1 auto;
            min-width: 0;
            gap: .75rem;
        }

        .spj-semantic-workspace form[data-spj-preparation-toolbar] > [data-spj-filter-actions] {
            flex: 0 0 auto;
            flex-wrap: nowrap;
            margin-top: 0;
        }

        .spj-semantic-workspace form[data-spj-preparation-toolbar] > [data-page-table-per-page] {
            flex: 0 0 auto;
            min-height: 2.25rem;
            margin-left: .25rem;
            padding-left: .75rem;
            border-left: 1px solid var(--ui-line);
            white-space: nowrap;
        }
    }
</style>

<script>
    (() => {
        const setupSpjPreparationToolbar = () => {
            const workspace = document.querySelector('.spj-semantic-workspace');
            if (!workspace) return;

            const tabInput = workspace.querySelector('input[name="tab"][value="persiapan"]');
            const form = tabInput?.closest('form');
            if (!form || form.dataset.spjPreparationToolbarInitialized === 'true') return;

            const perPage = workspace.querySelector('[data-page-table-per-page]');
            const filterGrid = form.querySelector(':scope > .grid');
            const actions = form.querySelector(':scope > .mt-3');
            if (!perPage || !filterGrid || !actions) return;

            form.dataset.spjPreparationToolbarInitialized = 'true';
            form.dataset.spjPreparationToolbar = 'true';
            filterGrid.dataset.spjFilterGrid = 'true';
            actions.dataset.spjFilterActions = 'true';

            const placeholder = document.createComment('spj-per-page-placeholder');
            perPage.before(placeholder);

            const desktop = window.matchMedia('(min-width: 1280px)');
            const placePerPage = () => {
                if (desktop.matches) {
                    form.append(perPage);
                } else if (placeholder.parentNode) {
                    placeholder.after(perPage);
                }
            };

            placePerPage();
            desktop.addEventListener?.('change', placePerPage);
        };

        requestAnimationFrame(setupSpjPreparationToolbar);
        document.addEventListener('livewire:navigated', setupSpjPreparationToolbar);
    })();
</script>
