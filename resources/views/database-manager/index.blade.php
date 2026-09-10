<x-layouts.tailwind-app>
    @php
        $fmtBytes = fn ($bytes) => $bytes < 1024 ? $bytes.' B' : ($bytes < 1048576 ? number_format($bytes / 1024, 1).' KB' : number_format($bytes / 1048576, 2).' MB');
        $healthClass = fn ($level) => match ($level) { 'ok' => 'db-health-ok', 'warning' => 'db-health-warning', default => 'db-health-danger' };
        $activeHealth = $activeStatus ? app(\App\Services\SchoolDatabaseManager::class)->health($activeStatus['school']) : null;
        $databaseCount = $list->count();
        $existingDatabaseCount = $list->filter(fn ($row) => $row['exists'])->count();
        $connectedDatabaseCount = $list->filter(fn ($row) => $row['connectionOk'])->count();
        $totalStorage = $list->sum(fn ($row) => (int) ($row['totalSize'] ?? 0));
        $activeTableCount = count($tables ?? []);
        $activeTableRows = collect($tables ?? [])->sum(fn ($row) => (int) ($row['count'] ?? 0));
        $integrityOk = $activeStatus && strtolower(trim((string) ($activeStatus['integrity'] ?? ''))) === 'ok';
        $connectionReady = (bool) ($active['school'] && $active['connected'] && $activeStatus);
        $lastMigrated = $activeStatus && !empty($activeStatus['lastMigrated']) ? $activeStatus['lastMigrated']->diffForHumans() : '—';
    @endphp

    <div id="database-control-center" class="db-control-center space-y-5">
        <x-page-header title="Pusat Kontrol Database Sekolah" subtitle="Pantau database aktif, kesehatan SQLite, struktur tabel, dan maintenance dari satu ruang kerja." kicker="PENGATURAN · DATABASE AKTIF">
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('schools.select')">Ganti sekolah</x-ui.button>
                <x-ui.button variant="secondary" :href="route('school-backups.index')">Backup &amp; Restore</x-ui.button>
                @if($active['school'])<x-ui.button :href="route('database-manager.reset-form')">Reset database</x-ui.button>@endif
            </x-slot:actions>
            <div class="db-hero-summary grid sm:grid-cols-2 xl:grid-cols-4">
                <div class="db-hero-stat"><p class="db-eyebrow">Database aktif</p><div class="mt-1 flex items-center gap-2"><span class="db-status-dot {{ $connectionReady ? 'is-ok' : 'is-danger' }}"></span><p class="font-bold text-[var(--ui-fg-strong)]">{{ $active['school']?->name ?? 'Belum dipilih' }}</p></div><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">NPSN {{ $active['school']?->npsn ?? '—' }} · Session {{ $active['schoolId'] ?? '—' }}</p></div>
                <div class="db-hero-stat"><p class="db-eyebrow">Kesehatan</p><p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $activeHealth ? strtoupper($activeHealth['level']) : 'BELUM TERSEDIA' }}</p><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $activeHealth && empty($activeHealth['issues']) ? 'Tidak ada masalah terdeteksi' : ($activeHealth ? implode(' · ', $activeHealth['issues']) : 'Aktifkan sekolah untuk health check') }}</p></div>
                <div class="db-hero-stat"><p class="db-eyebrow">Penyimpanan aktif</p><p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $activeStatus ? $fmtBytes($activeStatus['totalSize']) : '—' }}</p><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">DB {{ $activeStatus ? $fmtBytes($activeStatus['size']) : '—' }} · WAL {{ $activeStatus ? $fmtBytes($activeStatus['walSize']) : '—' }}</p></div>
                <div class="db-hero-stat"><p class="db-eyebrow">Struktur aktif</p><p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ number_format($activeTableCount, 0, ',', '.') }} tabel</p><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ number_format($activeTableRows, 0, ',', '.') }} baris terhitung</p></div>
            </div>
        </x-page-header>

        @if(!$active['school'])
            <x-ui.alert type="warning" title="Belum ada database sekolah aktif">Pilih sekolah terlebih dahulu untuk menampilkan health check, isi tabel, status migrasi, dan alat maintenance.</x-ui.alert>
        @elseif(!$active['connected'])
            <x-ui.alert type="danger" title="Koneksi database aktif bermasalah">{{ $active['error'] ?: 'Koneksi SQLite tidak dapat dibuka. Periksa file database dan permission.' }}</x-ui.alert>
        @elseif($activeHealth && !empty($activeHealth['issues']))
            <x-ui.alert type="warning" title="Database aktif memerlukan perhatian">{{ implode(' · ', $activeHealth['issues']) }}</x-ui.alert>
        @endif

        <section id="db-tabs" class="db-workspace">
            <div class="db-tabbar"><nav class="flex gap-1 overflow-x-auto" aria-label="Navigasi database">
                <button type="button" data-tab="overview" class="tab-btn">Ringkasan</button>
                <button type="button" data-tab="list" class="tab-btn">Database Sekolah <span class="db-tab-count">{{ $databaseCount }}</span></button>
                <button type="button" data-tab="tables" class="tab-btn">Explorer Tabel <span class="db-tab-count">{{ $activeTableCount }}</span></button>
                <button type="button" data-tab="diagnostic" class="tab-btn">Diagnostik</button>
                <button type="button" data-tab="maintenance" class="tab-btn">Maintenance</button>
            </nav></div>

            <div data-panel="overview" class="db-panel space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="db-metric-card"><div class="flex items-start justify-between gap-3"><div><p class="db-eyebrow">Database sekolah</p><p class="db-metric-value">{{ $databaseCount }}</p></div><span class="db-icon-tile">DB</span></div><p class="db-metric-hint">{{ $existingDatabaseCount }} file tersedia · {{ $connectedDatabaseCount }} dapat dikoneksi</p></article>
                    <article class="db-metric-card"><div class="flex items-start justify-between gap-3"><div><p class="db-eyebrow">Integritas</p><p class="db-metric-value {{ $integrityOk ? 'text-emerald-600' : ($activeStatus ? 'text-rose-600' : '') }}">{{ $activeStatus ? ($integrityOk ? 'OK' : 'Periksa') : '—' }}</p></div><span class="db-icon-tile">✓</span></div><p class="db-metric-hint">PRAGMA integrity_check database aktif</p></article>
                    <article class="db-metric-card"><div class="flex items-start justify-between gap-3"><div><p class="db-eyebrow">Total penyimpanan</p><p class="db-metric-value">{{ $fmtBytes($totalStorage) }}</p></div><span class="db-icon-tile">SSD</span></div><p class="db-metric-hint">Akumulasi DB, WAL, dan SHM seluruh sekolah</p></article>
                    <article class="db-metric-card"><div class="flex items-start justify-between gap-3"><div><p class="db-eyebrow">Migrasi aktif</p><p class="db-metric-value">{{ $lastMigrated }}</p></div><span class="db-icon-tile">↻</span></div><p class="db-metric-hint">Migrasi terakhir yang tercatat untuk tenant aktif</p></article>
                </div>

                @if($activeStatus)
                    <div class="grid gap-4 xl:grid-cols-[1.15fr_.85fr]">
                        <section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Status operasional</p><h2 class="db-card-title">Checklist database aktif</h2></div><span class="db-health-pill {{ $healthClass($activeHealth['level']) }}">{{ strtoupper($activeHealth['level']) }}</span></div><div class="db-check-list">
                            <div class="db-check-row"><span class="db-status-dot {{ $active['connected'] ? 'is-ok' : 'is-danger' }}"></span><div><strong>Koneksi SQLite</strong><p>{{ $active['connected'] ? 'Koneksi school dapat dibuka.' : 'Koneksi database gagal.' }}</p></div><span>{{ $active['connected'] ? 'Siap' : 'Error' }}</span></div>
                            <div class="db-check-row"><span class="db-status-dot {{ $activeStatus['exists'] ? 'is-ok' : 'is-danger' }}"></span><div><strong>File database</strong><p>{{ basename($activeStatus['path']) }}</p></div><span>{{ $activeStatus['exists'] ? 'Ada' : 'Hilang' }}</span></div>
                            <div class="db-check-row"><span class="db-status-dot {{ $activeStatus['isWritable'] ? 'is-ok' : 'is-danger' }}"></span><div><strong>Permission tulis</strong><p>Diperlukan untuk transaksi, WAL, migrasi, dan maintenance.</p></div><span>{{ $activeStatus['isWritable'] ? 'Writable' : 'Read only' }}</span></div>
                            <div class="db-check-row"><span class="db-status-dot {{ $integrityOk ? 'is-ok' : 'is-danger' }}"></span><div><strong>Integrity check</strong><p>PRAGMA integrity_check: {{ $activeStatus['integrity'] ?? '—' }}</p></div><span>{{ $integrityOk ? 'Normal' : 'Periksa' }}</span></div>
                        </div></section>
                        <section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Penyimpanan</p><h2 class="db-card-title">Komposisi file SQLite</h2></div></div><div class="space-y-3 p-4">@php($totalActiveBytes = max(1, (int) $activeStatus['totalSize'])) @foreach([['label'=>'Database utama','value'=>(int)$activeStatus['size']],['label'=>'WAL','value'=>(int)$activeStatus['walSize']],['label'=>'SHM','value'=>(int)$activeStatus['shmSize']]] as $part)<div><div class="flex justify-between gap-3 text-xs"><span class="font-semibold">{{ $part['label'] }}</span><span class="font-mono text-[var(--ui-fg-muted)]">{{ $fmtBytes($part['value']) }}</span></div><div class="db-progress mt-1"><span style="width: {{ min(100, max(1, ($part['value'] / $totalActiveBytes) * 100)) }}%"></span></div></div>@endforeach<div class="db-path-box"><p class="db-eyebrow">Lokasi file</p><p class="mt-1 break-all font-mono text-xs">{{ $activeStatus['path'] }}</p></div></div></section>
                    </div>
                    <div class="grid gap-4 xl:grid-cols-[.9fr_1.1fr]">
                        <section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Data utama</p><h2 class="db-card-title">Ringkasan jumlah record</h2></div></div><div class="db-table-counts">@forelse($activeStatus['tableCounts'] as $name=>$count)<div><span class="font-mono">{{ $name }}</span><strong>{{ $count ?? 'error' }}</strong></div>@empty<p class="p-4 text-sm text-[var(--ui-fg-muted)]">Belum ada table count.</p>@endforelse</div></section>
                        <section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Aksi cepat</p><h2 class="db-card-title">Tindakan penting</h2></div></div><div class="grid gap-3 p-4 sm:grid-cols-2">
                            <form method="POST" action="{{ route('database-manager.integrity', $activeStatus['school']->id) }}" class="db-action-card">@csrf<button type="submit"><strong>Periksa integritas</strong><span>Jalankan sebelum maintenance atau saat ada indikasi data bermasalah.</span></button></form>
                            <form method="POST" action="{{ route('database-manager.checkpoint', $activeStatus['school']->id) }}" class="db-action-card">@csrf<button type="submit"><strong>Checkpoint WAL</strong><span>Flush perubahan WAL ke file database utama.</span></button></form>
                            <form method="POST" action="{{ route('database-manager.migrate', $activeStatus['school']->id) }}" class="db-action-card">@csrf<button type="submit"><strong>Jalankan migrasi</strong><span>Sinkronkan struktur tenant dengan migration school terbaru.</span></button></form>
                            <a href="{{ route('school-backups.index') }}" class="db-action-card"><strong>Backup sebelum perubahan</strong><span>Buka modul backup & restore sebelum tindakan berisiko.</span></a>
                        </div></section>
                    </div>
                @endif
            </div>

            <div data-panel="list" class="hidden db-panel p-0">
                <div class="db-section-toolbar"><div><p class="db-eyebrow">Seluruh tenant sekolah</p><h2 class="db-card-title">Database Sekolah</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Aktifkan koneksi, cek keberadaan file, dan migrasi per sekolah.</p></div><label class="db-search"><span>Cari</span><input id="database-school-search" type="search" placeholder="Nama sekolah atau NPSN"></label></div>
                <div class="overflow-x-auto"><table class="db-data-table min-w-[900px] w-full"><thead><tr><th>Sekolah</th><th>Database</th><th class="text-right">Ukuran</th><th>Koneksi</th><th>Health</th><th class="text-right">Tindakan</th></tr></thead><tbody id="database-school-list">@foreach($list as $row) @php($rowHealth=app(\App\Services\SchoolDatabaseManager::class)->health($row['school']))<tr data-search="{{ strtolower($row['school']->name.' '.$row['school']->npsn) }}" class="{{ $row['isActive'] ? 'is-active' : '' }}"><td><div class="flex items-center gap-3"><span class="db-status-dot {{ $row['isActive'] ? 'is-ok' : '' }}"></span><div><div class="flex items-center gap-2"><strong>{{ $row['school']->name }}</strong>@if($row['isActive'])<span class="db-active-badge">AKTIF</span>@endif</div><p>NPSN {{ $row['school']->npsn }} · ID {{ $row['school']->id }}</p></div></div></td><td><p class="font-mono text-xs">{{ $row['exists'] ? basename($row['path']) : 'File belum tersedia' }}</p><p>{{ $row['exists'] ? 'File ditemukan' : 'Perlu provision' }}</p></td><td class="text-right font-mono text-xs">{{ $fmtBytes($row['totalSize']) }}</td><td><span class="db-health-pill {{ $row['connectionOk'] ? 'db-health-ok' : 'db-health-danger' }}">{{ $row['connectionOk'] ? 'TERHUBUNG' : 'ERROR' }}</span></td><td><span class="db-health-pill {{ $healthClass($rowHealth['level']) }}">{{ strtoupper($rowHealth['level']) }}</span></td><td><div class="flex justify-end gap-2">@if(!$row['isActive'])<form method="POST" action="{{ route('database-manager.activate', $row['school']->id) }}">@csrf<x-ui.button type="submit">Aktifkan</x-ui.button></form>@endif<form method="POST" action="{{ route('database-manager.migrate', $row['school']->id) }}">@csrf<x-ui.button type="submit" variant="secondary">Migrasi</x-ui.button></form></div></td></tr>@endforeach</tbody></table></div>
                <div id="database-school-empty" class="hidden db-empty-state">Tidak ada sekolah yang cocok dengan pencarian.</div>
            </div>

            <div data-panel="tables" class="hidden db-panel space-y-4">
                @if(!$active['school'])<div class="db-empty-state"><strong>Belum ada database aktif.</strong><span>Aktifkan sekolah sebelum membuka Explorer Tabel.</span></div>
                @elseif(!empty($tableError))<div class="m-4"><x-ui.alert type="danger" title="Explorer tabel tidak dapat dibuka">{{ $tableError }}</x-ui.alert></div>
                @else
                    <div class="db-table-single" data-guide-open="{{ $table }}">
                        <div class="mb-3 flex flex-wrap items-end justify-between gap-3"><div><p class="db-eyebrow">Explorer · {{ count($tables) }} tabel</p><h3 class="text-base font-bold text-[var(--ui-fg-strong)]">Daftar tabel database</h3><p class="mt-1 max-w-2xl text-xs leading-5 text-[var(--ui-fg-muted)]">{{ $active['school']->name }} · Klik <strong>Buka</strong> pada baris untuk melihat struktur kolom dan contoh isi langsung di tempat. Semua baca-saja, tanpa mengubah data.</p></div><input id="guide-search" type="search" placeholder="Cari nama, keterangan, atau kelompok…" class="ui-input w-full sm:max-w-xs"></div><div class="overflow-hidden rounded-xl border border-[var(--ui-line)]"><div class="overflow-x-auto"><table class="db-data-table w-full min-w-[860px]"><thead><tr><th data-guide-sort="name">Tabel <span class="sort-icon">↕</span></th><th>Kelompok</th><th data-guide-sort="rows" class="text-right">Baris <span class="sort-icon">↕</span></th><th class="text-right">Kolom</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody id="guide-table-body">@foreach($tables as $t)<tr data-guide-name="{{ strtolower($t['name'].' '.($t['label'] ?? '').' '.($t['group'] ?? '')) }}" data-guide-rows="{{ $t['count'] ?? 0 }}" data-guide-table="{{ $t['name'] }}"><td><p class="text-sm font-bold text-[var(--ui-fg-strong)]">{{ $t['label'] ?? $t['name'] }}</p><p class="truncate font-mono text-[11px] text-[var(--ui-fg-muted)]">{{ $t['name'] }}</p><p class="mt-0.5 line-clamp-2 max-w-md text-[11px] text-[var(--ui-fg-muted)]">{{ $t['blurb'] ?? '' }}</p></td><td><span class="db-health-pill">{{ $t['group'] ?? 'Sistem' }}</span></td><td class="text-right font-mono text-xs">{{ $t['count'] ?? '—' }}</td><td class="text-right font-mono text-xs">{{ $t['columns'] ?? '—' }}</td><td class="text-right"><button type="button" data-guide-toggle="{{ $t['name'] }}" class="ui-btn ui-btn-secondary !min-h-0 !px-2 !py-1 !text-xs">Buka</button></td></tr><tr data-guide-detail="{{ $t['name'] }}" class="hidden"><td colspan="5"><div data-guide-detail-body="{{ $t['name'] }}" class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 text-sm text-[var(--ui-fg-muted)]">Memuat…</div></td></tr>@endforeach</tbody></table></div><div class="db-table-pagination"><span id="guide-pagination-info"></span><div class="flex gap-1"><button id="guide-prev" type="button" class="ui-btn ui-btn-secondary !min-h-0 !px-2 !py-1 !text-xs">‹</button><button id="guide-next" type="button" class="ui-btn ui-btn-secondary !min-h-0 !px-2 !py-1 !text-xs">›</button></div></div></div><div id="guide-empty" class="hidden db-empty-state">Tidak ada tabel yang cocok dengan pencarian.</div>
                        {{-- Panel detail dua-kolom dihapus: struktur + contoh isi kini expand langsung pada baris master di atas. --}}
                    </div>
                @endif
            </div>

            <div data-panel="diagnostic" class="hidden db-panel space-y-4">@if($activeStatus)<div class="grid gap-4 lg:grid-cols-2"><section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Kesehatan file</p><h2 class="db-card-title">Diagnostik SQLite</h2></div><span class="db-health-pill {{ $integrityOk?'db-health-ok':'db-health-danger' }}">{{ $activeStatus['integrity'] ?? '—' }}</span></div><div class="db-diagnostic-list"><div><span>File tersedia</span><strong>{{ $activeStatus['exists']?'Ya':'Tidak' }}</strong></div><div><span>Writable</span><strong>{{ $activeStatus['isWritable']?'Ya':'Tidak' }}</strong></div><div><span>Database</span><strong>{{ $fmtBytes($activeStatus['size']) }}</strong></div><div><span>WAL</span><strong>{{ $fmtBytes($activeStatus['walSize']) }}</strong></div><div><span>SHM</span><strong>{{ $fmtBytes($activeStatus['shmSize']) }}</strong></div><div><span>Status</span><strong>{{ $activeStatus['status'] ?? '—' }}</strong></div></div><div class="p-4 pt-0"><form method="POST" action="{{ route('database-manager.integrity',$activeStatus['school']->id) }}">@csrf<x-ui.button type="submit">Jalankan integrity check</x-ui.button></form></div></section><section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Teknis</p><h2 class="db-card-title">Koneksi &amp; path</h2></div></div><div class="space-y-3 p-4"><div class="db-path-box"><p class="db-eyebrow">Connection</p><p class="mt-1 font-mono text-xs">database.connections.school</p></div><div class="db-path-box"><p class="db-eyebrow">Database path</p><p class="mt-1 break-all font-mono text-xs">{{ $activeStatus['path'] }}</p></div>@if($activeStatus['connectionError'])<x-ui.alert type="danger" title="Connection error">{{ $activeStatus['connectionError'] }}</x-ui.alert>@endif</div></section></div><section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Record penting</p><h2 class="db-card-title">Table count yang dipantau</h2></div></div><div class="db-table-counts db-table-counts-wide">@foreach($activeStatus['tableCounts'] as $name=>$count)<div><span class="font-mono">{{ $name }}</span><strong>{{ $count ?? 'error' }}</strong></div>@endforeach</div></section>@else<div class="db-empty-state">Tidak ada database aktif untuk didiagnostik.</div>@endif</div>

            <div data-panel="maintenance" class="hidden db-panel space-y-4">@if($activeStatus)<div class="grid gap-4 xl:grid-cols-3"><section class="db-maintenance-card is-safe"><p class="db-eyebrow">Rutin / aman</p><h2>Checkpoint WAL</h2><p>Flush WAL ke file database utama. Cocok sebelum backup atau saat WAL membesar.</p><form method="POST" action="{{ route('database-manager.checkpoint',$activeStatus['school']->id) }}">@csrf<x-ui.button type="submit">Jalankan checkpoint</x-ui.button></form></section><section class="db-maintenance-card is-caution"><p class="db-eyebrow">Struktur</p><h2>Migrasi database</h2><p>Terapkan migration school terbaru pada tenant aktif.</p><form method="POST" action="{{ route('database-manager.migrate',$activeStatus['school']->id) }}">@csrf<x-ui.button type="submit">Jalankan migrasi</x-ui.button></form></section><section class="db-maintenance-card is-caution"><p class="db-eyebrow">Optimasi</p><h2>VACUUM SQLite</h2><p>Reclaim space dan defragment file; dapat mengunci database sementara.</p><form method="POST" action="{{ route('database-manager.vacuum',$activeStatus['school']->id) }}" data-confirm="VACUUM akan mengunci database sebentar. Pastikan backup tersedia. Lanjutkan?">@csrf<x-ui.button type="submit">Jalankan VACUUM</x-ui.button></form></section></div><section class="db-card"><div class="db-card-header"><div><p class="db-eyebrow">Pemulihan struktur</p><h2 class="db-card-title">Provision &amp; backup</h2></div></div><div class="grid gap-3 p-4 md:grid-cols-2"><form method="POST" action="{{ route('database-manager.provision',$activeStatus['school']->id) }}" class="db-action-card" data-confirm="Provision akan membuat file bila hilang dan menjalankan migrasi. Lanjutkan?">@csrf<button type="submit"><strong>Provision database</strong><span>Buat ulang struktur bila file tenant belum tersedia tanpa reset total.</span></button></form><a href="{{ route('school-backups.index') }}" class="db-action-card"><strong>Backup &amp; Restore</strong><span>Buat backup sebelum migrasi besar, VACUUM, atau koreksi struktural.</span></a></div></section><section class="db-danger-zone"><div><p class="db-eyebrow">Zona berbahaya</p><h2>Reset total database sekolah aktif</h2><p>Menghapus seluruh data tenant, sequence, WAL/SHM, lalu membangun database dari nol.</p></div><x-ui.button :href="route('database-manager.reset-form')">Buka halaman reset</x-ui.button></section>@else<div class="db-empty-state">Pilih sekolah aktif sebelum menjalankan maintenance.</div>@endif</div>
        </section>

        <section class="db-connection-footnote"><div><p class="db-eyebrow">Sumber koneksi</p><strong>SchoolDatabaseManager adalah sumber kebenaran koneksi tenant.</strong><p>Jangan mengubah config/database.php manual. Aktivasi sekolah memperbarui session dan koneksi school secara terpusat.</p></div><code>{{ $active['database'] ?: 'database belum aktif' }}</code></section>
    </div>

    <script>
        (() => {
            const root = document.getElementById('db-tabs'); if (!root) return;
            const buttons = root.querySelectorAll('[data-tab]'); const panels = root.querySelectorAll('[data-panel]'); const valid = ['overview','list','tables','diagnostic','maintenance'];
            const setTab = name => { buttons.forEach(b => b.dataset.active=(b.dataset.tab===name).toString()); panels.forEach(p => p.classList.toggle('hidden',p.dataset.panel!==name)); localStorage.setItem('db-manager-tab',name); history.replaceState(null,'','#'+name); };
            buttons.forEach(b=>b.addEventListener('click',()=>setTab(b.dataset.tab))); let initial=location.hash.replace('#','')||localStorage.getItem('db-manager-tab')||'overview'; if(new URLSearchParams(location.search).has('table')) initial='tables'; setTab(valid.includes(initial)?initial:'overview');
            const schoolSearch=document.getElementById('database-school-search'); const schoolRows=[...document.querySelectorAll('#database-school-list tr')]; const schoolEmpty=document.getElementById('database-school-empty'); if(schoolSearch) schoolSearch.addEventListener('input',()=>{ const q=schoolSearch.value.toLowerCase().trim(); let visible=0; schoolRows.forEach(row=>{ const match=!q||(row.dataset.search||'').includes(q); row.hidden=!match; if(match) visible++; }); schoolEmpty?.classList.toggle('hidden',visible>0); });
            const search=document.getElementById('table-search'); const tbody=document.getElementById('table-list-body'); const prev=document.getElementById('table-prev'); const next=document.getElementById('table-next'); const info=document.getElementById('table-pagination-info');
            if(tbody){ const all=[...tbody.querySelectorAll('tr')]; let filtered=[...all],page=1,sortKey='name',sortDir='asc'; const perPage=15; const sort=()=>filtered.sort((a,b)=>{let av=sortKey==='rows'?parseInt(a.dataset.rows||'0',10):(a.dataset.name||''),bv=sortKey==='rows'?parseInt(b.dataset.rows||'0',10):(b.dataset.name||''); if(sortKey==='name') return sortDir==='asc'?String(av).localeCompare(String(bv)):String(bv).localeCompare(String(av)); return sortDir==='asc'?av-bv:bv-av;}); const render=()=>{all.forEach(r=>r.style.display='none'); const total=filtered.length,pages=Math.max(1,Math.ceil(total/perPage)); page=Math.min(Math.max(page,1),pages); const start=(page-1)*perPage; filtered.slice(start,start+perPage).forEach(r=>r.style.display=''); if(info) info.textContent=total?`${start+1}–${Math.min(start+perPage,total)} dari ${total}`:'Tidak ada tabel'; if(prev) prev.disabled=page<=1; if(next) next.disabled=page>=pages;}; const apply=()=>{const q=(search?.value||'').toLowerCase().trim(); filtered=all.filter(r=>!q||(r.dataset.name||'').includes(q)); page=1; sort(); render();}; search?.addEventListener('input',apply); root.querySelectorAll('th[data-sort]').forEach(th=>th.addEventListener('click',()=>{const key=th.dataset.sort;if(sortKey===key)sortDir=sortDir==='asc'?'desc':'asc';else{sortKey=key;sortDir='asc';}root.querySelectorAll('th[data-sort] .sort-icon').forEach(i=>i.textContent='↕');th.querySelector('.sort-icon').textContent=sortDir==='asc'?'↑':'↓';sort();render();})); prev?.addEventListener('click',()=>{page--;render();}); next?.addEventListener('click',()=>{page++;render();}); apply(); }
            const tmButtons=root.querySelectorAll('.tm-tab'),tmPanels=root.querySelectorAll('[data-tm-panel]'); const setInner=name=>{tmButtons.forEach(b=>b.dataset.active=(b.dataset.tmTab===name).toString());tmPanels.forEach(p=>p.classList.toggle('hidden',p.dataset.tmPanel!==name));localStorage.setItem('tm-inner-tab',name);}; tmButtons.forEach(b=>b.addEventListener('click',()=>setInner(b.dataset.tmTab))); if(tmButtons.length){let saved=localStorage.getItem('tm-inner-tab')||'schema';if(!root.querySelector('[data-tm-panel="data"] table'))saved='schema';setInner(['schema','data'].includes(saved)?saved:'schema');}
        })();
    </script>
    <script>
        (() => {
            const panel = document.querySelector('[data-panel="tables"]'); if (!panel) return;
            const tbody = document.getElementById('guide-table-body'); if (!tbody) return;
            const search = document.getElementById('guide-search');
            const prev = document.getElementById('guide-prev');
            const next = document.getElementById('guide-next');
            const info = document.getElementById('guide-pagination-info');
            const empty = document.getElementById('guide-empty');
            const summaryUrl = @js(route('database-manager.table-summary', ['table' => '__TABLE__']));
            const masters = [...tbody.querySelectorAll('tr[data-guide-table]')];
            const cache = new Map();
            let filtered = [...masters], page = 1, sortKey = 'name', sortDir = 'asc', openTable = null;
            const perPage = 15;

            const el = (tag, text, cls) => {
                const node = document.createElement(tag);
                if (cls) node.className = cls;
                if (text !== undefined && text !== null) node.textContent = text;
                return node;
            };
            const short = (value) => {
                const text = value === null || value === undefined ? 'NULL' : String(value);
                return text.length > 80 ? text.slice(0, 80) + '…' : text;
            };

            const detailRow = (name) => tbody.querySelector(`tr[data-guide-detail="${CSS.escape(name)}"]`);
            const toggleBtn = (name) => tbody.querySelector(`[data-guide-toggle="${CSS.escape(name)}"]`);

            const paintButtons = () => {
                masters.forEach((row) => {
                    const btn = row.querySelector('[data-guide-toggle]');
                    if (btn) btn.textContent = openTable === row.dataset.guideTable ? 'Tutup' : 'Buka';
                });
            };

            const renderDetail = (name, body, data) => {
                body.innerHTML = '';
                body.appendChild(el('p', data.meta.blurb || '', 'text-xs leading-5 mb-3'));
                const sub = (title) => body.appendChild(el('p', title, 'text-[11px] font-bold uppercase tracking-wide mb-2'));
                sub(`Struktur kolom (${data.columns.length})`);
                const schemaTable = el('table', null, 'db-data-table w-full mb-4');
                const thead = el('thead'); const headRow = el('tr');
                ['Nama kolom', 'Type', 'Keterangan'].forEach((h) => headRow.appendChild(el('th', h)));
                thead.appendChild(headRow); schemaTable.appendChild(thead);
                const schemaBody = el('tbody');
                data.columns.forEach((col) => {
                    const tr = el('tr');
                    tr.appendChild(el('td', col.name, 'font-mono font-semibold text-xs'));
                    tr.appendChild(el('td', col.type, 'font-mono text-xs'));
                    const flags = [col.pk ? 'Kunci utama' : null, col.required ? 'Wajib diisi' : null].filter(Boolean).join(' · ') || '—';
                    tr.appendChild(el('td', flags, 'text-xs'));
                    schemaBody.appendChild(tr);
                });
                schemaTable.appendChild(schemaBody);
                const schemaWrap = el('div', null, 'overflow-x-auto mb-4'); schemaWrap.appendChild(schemaTable);
                body.appendChild(schemaWrap);
                sub(`Contoh isi (10 pertama dari ${data.total} baris)`);
                if (!data.rows.length) {
                    body.appendChild(el('p', 'Tabel ini belum memiliki data.', 'text-xs'));
                    return;
                }
                const dataWrap = el('div', null, 'overflow-x-auto max-h-[320px] overflow-auto');
                const dataTable = el('table', null, 'db-data-table w-full');
                const dataHead = el('thead'); const dataHeadRow = el('tr');
                Object.keys(data.rows[0]).forEach((key) => dataHeadRow.appendChild(el('th', key)));
                dataHead.appendChild(dataHeadRow); dataTable.appendChild(dataHead);
                const dataBody = el('tbody');
                data.rows.forEach((row) => {
                    const tr = el('tr');
                    Object.values(row).forEach((value) => {
                        const td = el('td', short(value), 'max-w-[220px] truncate font-mono text-xs');
                        td.title = value === null || value === undefined ? '' : String(value);
                        tr.appendChild(td);
                    });
                    dataBody.appendChild(tr);
                });
                dataTable.appendChild(dataBody); dataWrap.appendChild(dataTable);
                body.appendChild(dataWrap);
            };

            const openDetail = (name) => {
                const row = detailRow(name);
                if (!row) return;
                const body = row.querySelector('[data-guide-detail-body]');
                openTable = name;
                paintButtons();
                row.classList.remove('hidden');
                if (!body || cache.has(name)) return;
                body.textContent = 'Memuat struktur dan contoh isi…';
                fetch(summaryUrl.replace('__TABLE__', encodeURIComponent(name)), { headers: { Accept: 'application/json' } })
                    .then((response) => { if (!response.ok) throw new Error('HTTP ' + response.status); return response.json(); })
                    .then((data) => { cache.set(name, true); renderDetail(name, body, data); })
                    .catch(() => { body.textContent = 'Gagal memuat detail tabel. Coba lagi.'; });
            };

            const closeDetail = () => {
                openTable = null;
                paintButtons();
                tbody.querySelectorAll('tr[data-guide-detail]').forEach((row) => row.classList.add('hidden'));
            };

            const sort = () => filtered.sort((a, b) => {
                const av = sortKey === 'rows' ? parseInt(a.dataset.guideRows || '0', 10) : (a.dataset.guideName || '');
                const bv = sortKey === 'rows' ? parseInt(b.dataset.guideRows || '0', 10) : (b.dataset.guideName || '');
                if (sortKey === 'name') return sortDir === 'asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
                return sortDir === 'asc' ? av - bv : bv - av;
            });

            const render = () => {
                masters.forEach((row) => { row.style.display = 'none'; });
                tbody.querySelectorAll('tr[data-guide-detail]').forEach((row) => row.classList.add('hidden'));
                const total = filtered.length, pages = Math.max(1, Math.ceil(total / perPage));
                page = Math.min(Math.max(page, 1), pages);
                const start = (page - 1) * perPage;
                filtered.slice(start, start + perPage).forEach((row) => { row.style.display = ''; });
                const openRow = openTable && filtered.slice(start, start + perPage).find((row) => row.dataset.guideTable === openTable);
                if (openRow) {
                    const detail = detailRow(openTable);
                    if (detail) {
                        detail.style.display = '';
                        detail.classList.remove('hidden');
                        openRow.after(detail);
                    }
                } else if (openTable) {
                    closeDetail();
                }
                if (info) info.textContent = total ? `${start + 1}–${Math.min(start + perPage, total)} dari ${total}` : 'Tidak ada tabel';
                if (prev) prev.disabled = page <= 1;
                if (next) next.disabled = page >= pages;
                if (empty) empty.classList.toggle('hidden', total > 0);
                paintButtons();
            };

            const apply = () => {
                const q = (search?.value || '').toLowerCase().trim();
                filtered = masters.filter((row) => !q || (row.dataset.guideName || '').includes(q));
                page = 1;
                sort();
                render();
            };

            tbody.addEventListener('click', (event) => {
                const btn = event.target.closest('[data-guide-toggle]');
                if (!btn) return;
                const name = btn.dataset.guideToggle;
                if (openTable === name) closeDetail();
                else openDetail(name);
            });
            search?.addEventListener('input', apply);
            panel.querySelectorAll('th[data-guide-sort]').forEach((th) => th.addEventListener('click', () => {
                const key = th.dataset.guideSort;
                if (sortKey === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                else { sortKey = key; sortDir = 'asc'; }
                panel.querySelectorAll('th[data-guide-sort] .sort-icon').forEach((icon) => { icon.textContent = '↕'; });
                const icon = th.querySelector('.sort-icon');
                if (icon) icon.textContent = sortDir === 'asc' ? '↑' : '↓';
                sort();
                render();
            }));
            prev?.addEventListener('click', () => { page--; render(); });
            next?.addEventListener('click', () => { page++; render(); });

            apply();
            const deepLink = new URLSearchParams(location.search).get('table');
            if (deepLink && masters.some((row) => row.dataset.guideTable === deepLink)) openDetail(deepLink);
        })();
    </script>
</x-layouts.tailwind-app>
