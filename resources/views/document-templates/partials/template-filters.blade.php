<form method="GET" class="grid gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4 sm:grid-cols-[12rem_minmax(12rem,1fr)_auto_auto] sm:items-end">
    <x-ui.field label="Tampilkan status" for="status">
        <x-ui.select id="status" name="status">
            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Semua Status</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Tidak Aktif</option>
        </x-ui.select>
    </x-ui.field>
    <x-ui.field label="Tampilkan kategori" for="category">
        <x-ui.select id="category" name="category">
            <option value="">Semua Kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $labels[$category] ?? ucwords(strtolower(str_replace('_', ' ', $category))) }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>
    <x-ui.button type="submit">Tampilkan</x-ui.button>
    <x-ui.button variant="secondary" :href="route('document-templates.index')">Hapus Filter</x-ui.button>
</form>
