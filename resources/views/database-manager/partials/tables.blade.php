<div data-panel="list" class="space-y-4">
    @if($active['school'])
        <div class="db-card-header">
            <div>
                <p class="db-eyebrow">Explore Database Sekolah</p>
                <h2 class="db-card-title">Tabel Database Sekolah</h2>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Lihat struktur dan contoh data database sekolah aktif.</p>
            </div>
        </div>
        <livewire:database-table-explorer :tables="$tables" :initial-table="$table" database="school" wire:key="database-explorer-school" />
    @else
        <div class="db-panel"><div class="db-empty-state">Pilih sekolah aktif sebelum membuka Explorer Database Sekolah.</div></div>
    @endif
</div>

<div data-panel="central" class="space-y-4" hidden>
    <div class="db-card-header">
        <div>
            <p class="db-eyebrow">Explore Database Pusat</p>
            <h2 class="db-card-title">Tabel Database Pusat</h2>
            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Lihat struktur dan contoh data reference database pusat.</p>
        </div>
    </div>
    <livewire:database-table-explorer :tables="$centralTables" database="central" wire:key="database-explorer-central" />
</div>
