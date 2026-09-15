<div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-3 sm:divide-x sm:divide-y-0">
    <x-stat-item label="Template canonical" :value="number_format($canonicalTemplateCount, 0, ',', '.')" hint="Jumlah sheet pada paket master"
        value-class="text-[var(--theme-content-accent)]" />
    <x-stat-item label="Kategori SPJ" :value="number_format(count($categories), 0, ',', '.')" hint="Kategori canonical yang dapat dihubungkan"
        value-class="text-emerald-700" />
    <x-stat-item label="Penanda Data" :value="number_format($placeholderCount, 0, ',', '.')" hint="Penanda yang dikenal oleh engine template"
        value-class="text-[var(--ui-fg-strong)]" />
</div>
