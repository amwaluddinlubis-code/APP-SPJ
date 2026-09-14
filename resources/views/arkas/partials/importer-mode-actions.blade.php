<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('arkas.importer', array_filter(['table' => $selectedTable, 'limit' => $limit])) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color: {{ $mode === 'simple' ? 'var(--theme-content-accent)' : 'var(--ui-line)' }}; color: var(--ui-fg-strong); background: var(--ui-bg)">Sederhana</a>
    <a href="{{ route('arkas.importer', array_filter(['table' => $selectedTable, 'limit' => $limit, 'mode' => 'advanced'])) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color: {{ $mode === 'advanced' ? 'var(--theme-content-accent)' : 'var(--ui-line)' }}; color: var(--ui-fg-strong); background: var(--ui-bg)">Lanjutan</a>
    <x-ui.button variant="secondary" :href="route('arkas.settings')">Pengaturan Sumber</x-ui.button>
</div>
