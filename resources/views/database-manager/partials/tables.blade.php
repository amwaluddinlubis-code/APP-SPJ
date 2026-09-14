@if($active['school'])
    <livewire:database-table-explorer :tables="$tables" :initial-table="$table" />
@else
    <div data-panel="tables" class="db-panel"><div class="db-empty-state">Pilih sekolah aktif sebelum membuka Explorer Tabel.</div></div>
@endif
