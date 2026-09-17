<form method="GET" class="grid gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4 sm:grid-cols-[12rem_minmax(12rem,1fr)_auto_auto] sm:items-end">
    <x-ui.field label="Tampilkan status" for="status">
        <x-ui.searchable-select id="status" name="status"
            :options="[
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Tidak Aktif'],
            ]" :value="$filters['status'] ?? 'all'" placeholder="Semua Status" search-placeholder="Cari status..." />
    </x-ui.field>
    <x-ui.field label="Tampilkan kategori" for="category">
        <x-ui.searchable-select id="category" name="category"
            :options="array_merge(
                [['value' => '', 'label' => 'Semua Kategori']],
                collect($categories)->map(fn ($category) => [
                    'value' => $category,
                    'label' => $labels[$category] ?? ucwords(strtolower(str_replace('_', ' ', $category))),
                ])->all(),
            )" :value="$filters['category'] ?? ''" placeholder="Semua Kategori" search-placeholder="Cari kategori..." />
    </x-ui.field>
    <x-ui.button type="submit">Tampilkan</x-ui.button>
    <x-ui.button variant="secondary" :href="route('document-templates.index')">Hapus Filter</x-ui.button>
</form>
