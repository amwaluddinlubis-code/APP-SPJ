<div data-livewire-diagnostics="true" data-panel="diagnostic" class="db-panel space-y-4">
    @if($hasDatabase)
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="db-card">
                <div class="db-card-header"><div><p class="db-eyebrow">Kesehatan file</p><h2 class="db-card-title">Diagnostik SQLite</h2></div><span class="db-health-pill {{ strtolower($integrity) === 'ok' ? 'db-health-ok' : 'db-health-danger' }}">{{ $integrity }}</span></div>
                <div class="db-diagnostic-list"><div><span>File tersedia</span><strong>{{ $exists ? 'Ya' : 'Tidak' }}</strong></div><div><span>Writable</span><strong>{{ $writable ? 'Ya' : 'Tidak' }}</strong></div><div><span>Database</span><strong>{{ $size }}</strong></div><div><span>WAL</span><strong>{{ $walSize }}</strong></div><div><span>SHM</span><strong>{{ $shmSize }}</strong></div><div><span>Status</span><strong>{{ $status }}</strong></div></div>
                <div class="p-4 pt-0"><form method="POST" action="{{ $integrityRoute }}">@csrf<x-ui.button type="submit">Jalankan integrity check</x-ui.button></form></div>
            </section>
            <section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Teknis</p><h2 class="db-card-title">Koneksi &amp; path</h2></div></div><div class="space-y-3 p-4"><div class="db-path-box"><p class="db-eyebrow">Connection</p><p class="mt-1 font-mono text-xs">database.connections.school</p></div><div class="db-path-box"><p class="db-eyebrow">Database path</p><p class="mt-1 break-all font-mono text-xs">{{ $path }}</p></div>@if($connectionError)<x-ui.alert type="danger" title="Connection error">{{ $connectionError }}</x-ui.alert>@endif</div></section>
        </div>
        <section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Record penting</p><h2 class="db-card-title">Jumlah record yang dipantau</h2></div></div><div class="db-table-counts db-table-counts-wide">@foreach($tableCounts as $name => $count)<div><span class="font-mono">{{ $name }}</span><strong>{{ $count ?? 'error' }}</strong></div>@endforeach</div></section>
    @else
        <div class="db-empty-state">Tidak ada database aktif untuk didiagnostik.</div>
    @endif
</div>
