<x-layouts.tailwind-app>
    @php
        $fmtBytes = fn ($bytes) => $bytes < 1024
            ? $bytes.' B'
            : ($bytes < 1048576 ? number_format($bytes / 1024, 1).' KB' : number_format($bytes / 1048576, 2).' MB');
        $healthClass = fn ($level) => match ($level) {
            'ok' => 'db-health-ok',
            'warning' => 'db-health-warning',
            default => 'db-health-danger',
        };
        $activeHealth = $activeStatus ? app(\App\Services\SchoolDatabaseManager::class)->health($activeStatus['school']) : null;
        $databaseCount = $list->count();
        $existingDatabaseCount = $list->filter(fn ($row) => $row['exists'])->count();
        $connectedDatabaseCount = $list->filter(fn ($row) => $row['connectionOk'])->count();
        $totalStorage = $list->sum(fn ($row) => (int) ($row['totalSize'] ?? 0));
        $activeTableCount = count($tables ?? []);
        $activeTableRows = collect($tables ?? [])->sum(fn ($row) => (int) ($row['count'] ?? 0));
        $integrityOk = $activeStatus && strtolower(trim((string) ($activeStatus['integrity'] ?? ''))) === 'ok';
        $connectionReady = $active['school'] && $active['connected'] && $activeStatus;
    @endphp

    <div id="database-control-center" class="db-control-center space-y-5">
        <x-page-header
            title="Pusat Kontrol Database Sekolah"
            subtitle="Pantau database aktif, kesehatan SQLite, struktur tabel, dan tindakan maintenance dari satu halaman."
            kicker="PENGATURAN · DATABASE AKTIF"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('schools.select')">Ganti sekolah</x-ui.button>
                <x-ui.button variant="secondary" :href="route('school-backups.index')">Backup &amp; Restore</x-ui.button>
                @if($active['school'])
                    <x-ui.button :href="route('database-manager.reset-form')">Reset database</x-ui.button>
                @endif
            </x-slot:actions>

            <div class="db-hero-summary grid sm:grid-cols-2 xl:grid-cols-4">
                <div class="db-hero-stat">
                    <p class="db-eyebrow">Database aktif</p>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="db-status-dot {{ $connectionReady ? 'is-ok' : 'is-danger' }}"></span>
                        <p class="font-bold text-[var(--ui-fg-strong)]">{{ $active['school']?->name ?? 'Belum dipilih' }}</p>
                    </div>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">NPSN {{ $active['school']?->npsn ?? '—' }} · Session {{ $active['schoolId'] ?? '—' }}</p>
                </div>
                <div class="db-hero-stat">
                    <p class="db-eyebrow">Kesehatan</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $activeHealth ? strtoupper($activeHealth['level']) : 'BELUM TERSEDIA' }}</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $activeHealth && empty($activeHealth['issues']) ? 'Tidak ada masalah terdeteksi' : ($activeHealth ? implode(' · ', $activeHealth['issues']) : 'Aktifkan sekolah untuk menjalankan health check') }}</p>
                </div>
                <div class="db-hero-stat">
                    <p class="db-eyebrow">Penyimpanan aktif</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $activeStatus ? $fmtBytes($activeStatus['totalSize']) : '—' }}</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">DB {{ $activeStatus ? $fmtBytes($activeStatus['size']) : '—' }} · WAL {{ $activeStatus ? $fmtBytes($activeStatus['walSize']) : '—' }}</p>
                </div>
                <div class="db-hero-stat">
                    <p class="db-eyebrow">Struktur aktif</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ number_format($activeTableCount, 0, ',', '.') }} tabel</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ number_format($activeTableRows, 0, ',', '.') }} baris terhitung dari seluruh tabel</p>
                </div>
            </div>
        </x-page-header>

        @if(!$active['school'])
            <x-ui.alert type="warning" title="Belum ada database sekolah aktif">
                Pilih sekolah terlebih dahulu. Setelah sekolah aktif, halaman ini akan menampilkan health check, isi tabel, status migrasi, dan alat maintenance.
            </x-ui.alert>
        @elseif(!$active['connected'])
            <x-ui.alert type="danger" title="Koneksi database aktif bermasalah">
                {{ $active['error'] ?: 'Koneksi SQLite tidak dapat dibuka. Periksa file database dan permission.' }}
            </x-ui.alert>
        @elseif($activeHealth && !empty($activeHealth['issues']))
            <x-ui.alert type="warning" title="Database aktif memerlukan perhatian">
                {{ implode(' · ', $activeHealth['issues']) }}
            </x-ui.alert>
        @endif

        <section id="db-tabs" class="db-workspace">
            <div class="db-tabbar">
                <nav class="flex gap-1 overflow-x-auto" aria-label="Navigasi database">
                    <button type="button" data-tab="overview" class="tab-btn">Ringkasan</button>
                    <button type="button" data-tab="list" class="tab-btn">Database Sekolah <span class="db-tab-count">{{ $databaseCount }}</span></button>
                    <button type="button" data-tab="tables" class="tab-btn">Explorer Tabel <span class="db-tab-count">{{ $activeTableCount }}</span></button>
                    <button type="button" data-tab="diagnostic" class="tab-btn">Diagnostik</button>
                    <button type="button" data-tab="maintenance" class="tab-btn">Maintenance</button>
                </nav>
            </div>

            <div data-panel="overview" class="db-panel space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="db-metric-card">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="db-eyebrow">Database sekolah</p><p class="db-metric-value">{{ $databaseCount }}</p></div>
                            <span class="db-icon-tile">DB</span>
                        </div>
                        <p class="db-metric-hint">{{ $existingDatabaseCount }} file tersedia · {{ $connectedDatabaseCount }} dapat dikoneksi</p>
                    </article>
                    <article class="db-metric-card">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="db-eyebrow">Integritas</p><p class="db-metric-value {{ $integrityOk ? 'text-emerald-600' : ($activeStatus ? 'text-rose-600' : '') }}">{{ $activeStatus ? ($integrityOk ? 'OK' : 'Periksa') : '—' }}</p></div>
                            <span class="db-icon-tile">✓</span>
                        </div>
                        <p class="db-metric-hint">PRAGMA integrity_check pada database yang sedang aktif</p>
                    </article>
                    <article class="db-metric-card">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="db-eyebrow">Total penyimpanan</p><p class="db-metric-value">{{ $fmtBytes($totalStorage) }}</p></div>
                            <span class="db-icon-tile">GB</span>
                        </div>
                        <p class="db-metric-hint">Akumulasi file SQLite, WAL, dan SHM seluruh sekolah</p>
                    </article>
                    <article class="db-metric-card">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="db-eyebrow">Migrasi aktif</p><p class="db-metric-value">{{ $activeStatus?->lastMigrated?->diffForHumans() ?? ($activeStatus['lastMigrated']?->diffForHumans() ?? '—') }}</p></div>
                            <span class="db-icon-tile">↻</span>
                        </div>
                        <p class="db-metric-hint">Waktu migrasi terakhir yang tercatat untuk sekolah aktif</p>
                    </article>
                </div>

                @if($activeStatus)
                    <div class="grid gap-4 xl:grid-cols-[1.15fr_.85fr]">
                        <section class="db-card">
                            <div class="db-card-header">
                                <div><p class="db-eyebrow">Status operasional</p><h2 class="db-card-title">Checklist database aktif</h2></div>
                                <span class="db-health-pill {{ $healthClass($activeHealth['level']) }}">{{ strtoupper($activeHealth['level']) }}</span>
                            </div>
                            <div class="db-check-list">
                                <div class="db-check-row"><span class="db-status-dot {{ $active['connected'] ? 'is-ok' : 'is-danger' }}"></span><div><strong>Koneksi SQLite</strong><p>{{ $active['connected'] ? 'Koneksi school dapat dibuka.' : 'Koneksi database gagal.' }}</p></div><span>{{ $active['connected'] ? 'Siap' : 'Error' }}</span></div>
                                <div class="db-check-row"><span class="db-status-dot {{ $activeStatus['exists'] ? 'is-ok' : 'is-danger' }}"></span><div><strong>File database</strong><p>{{ basename($activeStatus['path']) }}</p></div><span>{{ $activeStatus['exists'] ? 'Ada' : 'Hilang' }}</span></div>
                                <div class="db-check-row"><span class="db-status-dot {{ $activeStatus['isWritable'] ? 'is-ok' : 'is-danger' }}"></span><div><strong>Permission tulis</strong><p>Laravel perlu write access untuk transaksi, WAL, migrasi, dan maintenance.</p></div><span>{{ $activeStatus['isWritable'] ? 'Writable' : 'Read only' }}</span></div>
                                <div class="db-check-row"><span class="db-status-dot {{ $integrityOk ? 'is-ok' : 'is-danger' }}"></span><div><strong>Integrity check</strong><p>Hasil PRAGMA integrity_check: {{ $activeStatus['integrity'] ?? '—' }}</p></div><span>{{ $integrityOk ? 'Normal' : 'Periksa' }}</span></div>
                            </div>
                        </section>

                        <section class="db-card">
                            <div class="db-card-header"><div><p class="db-eyebrow">Penyimpanan</p><h2 class="db-card-title">Komposisi file SQLite</h2></div></div>
                            <div class="space-y-3 p-4">
                                @php($totalActiveBytes = max(1, (int) $activeStatus['totalSize']))
                                @foreach([
                                    ['label' => 'Database utama', 'value' => (int) $activeStatus['size']],
                                    ['label' => 'WAL', 'value' => (int) $activeStatus['walSize']],
                                    ['label' => 'SHM', 'value' => (int) $activeStatus['shmSize']],
                                ] as $storagePart)
                                    <div>
                                        <div class="flex items-center justify-between gap-3 text-xs"><span class="font-semibold text-[var(--ui-fg)]">{{ $storagePart['label'] }}</span><span class="font-mono text-[var(--ui-fg-muted)]">{{ $fmtBytes($storagePart['value']) }}</span></div>
                                        <div class="db-progress mt-1"><span style="width: {{ min(100, max(1, ($storagePart['value'] / $totalActiveBytes) * 100)) }}%"></span></div>
                                    </div>
                                @endforeach
                                <div class="db-path-box"><p class="db-eyebrow">Lokasi file</p><p class="mt-1 break-all font-mono text-xs">{{ $activeStatus['path'] }}</p></div>
                            </div>
                        </section>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[.9fr_1.1fr]">
                        <section class="db-card">
                            <div class="db-card-header"><div><p class="db-eyebrow">Data utama</p><h2 class="db-card-title">Ringkasan jumlah record</h2></div></div>
                            <div class="db-table-counts">
                                @forelse($activeStatus['tableCounts'] as $tableName => $count)
                                    <div><span class="font-mono">{{ $tableName }}</span><strong>{{ $count ?? 'error' }}</strong></div>
                                @empty
                                    <p class="p-4 text-sm text-[var(--ui-fg-muted)]">Belum ada table count yang tersedia.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="db-card">
                            <div class="db-card-header"><div><p class="db-eyebrow">Aksi cepat</p><h2 class="db-card-title">Tindakan yang paling sering digunakan</h2></div></div>
                            <div class="grid gap-3 p-4 sm:grid-cols-2">
                                <form method="POST" action="{{ route('database-manager.integrity', $activeStatus['school']->id) }}" class="db-action-card">@csrf<button type="submit"><strong>Periksa integritas</strong><span>Jalankan integrity_check sebelum maintenance atau ketika ada indikasi data rusak.</span></button></form>
                                <form method="POST" action="{{ route('database-manager.checkpoint', $activeStatus['school']->id) }}" class="db-action-card">@csrf<button type="submit"><strong>Checkpoint WAL</strong><span>Flush perubahan dari WAL ke file database utama.</span></button></form>
                                <form method="POST" action="{{ route('database-manager.migrate', $activeStatus['school']->id) }}" class="db-action-card">@csrf<button type="submit"><strong>Jalankan migrasi</strong><span>Pastikan struktur tenant mengikuti migration school terbaru.</span></button></form>
                                <a href="{{ route('school-backups.index') }}" class="db-action-card"><strong>Backup sebelum perubahan</strong><span>Buka modul backup & restore sebelum tindakan berisiko.</span></a>
                            </div>
                        </section>
                    </div>
                @endif
            </div>

            <div data-panel="list" class="hidden db-panel p-0">
                <div class="db-section-toolbar">
                    <div><p class="db-eyebrow">Seluruh tenant sekolah</p><h2 class="db-card-title">Database Sekolah</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Aktifkan koneksi, cek keberadaan file, dan jalankan migrasi per sekolah.</p></div>
                    <label class="db-search"><span>Cari</span><input id="database-school-search" type="search" placeholder="Nama sekolah atau NPSN"></label>
                </div>
                <div class="overflow-x-auto">
                    <table class="db-data-table min-w-[900px] w-full">
                        <thead><tr><th>Sekolah</th><th>Database</th><th class="text-right">Ukuran</th><th>Koneksi</th><th>Health</th><th class="text-right">Tindakan</th></tr></thead>
                        <tbody id="database-school-list">
                            @foreach($list as $row)
                                @php($rowHealth = app(\App\Services\SchoolDatabaseManager::class)->health($row['school']))
                                <tr data-search="{{ strtolower($row['school']->name.' '.$row['school']->npsn) }}" class="{{ $row['isActive'] ? 'is-active' : '' }}">
                                    <td><div class="flex items-center gap-3"><span class="db-status-dot {{ $row['isActive'] ? 'is-ok' : '' }}"></span><div><div class="flex items-center gap-2"><strong>{{ $row['school']->name }}</strong>@if($row['isActive'])<span class="db-active-badge">AKTIF</span>@endif</div><p>NPSN {{ $row['school']->npsn }} · ID {{ $row['school']->id }}</p></div></div></td>
                                    <td><p class="font-mono text-xs">{{ $row['exists'] ? basename($row['path']) : 'File belum tersedia' }}</p><p>{{ $row['exists'] ? 'File ditemukan' : 'Perlu provision' }}</p></td>
                                    <td class="text-right font-mono text-xs">{{ $fmtBytes($row['totalSize']) }}</td>
                                    <td><span class="db-health-pill {{ $row['connectionOk'] ? 'db-health-ok' : 'db-health-danger' }}">{{ $row['connectionOk'] ? 'TERHUBUNG' : 'ERROR' }}</span></td>
                                    <td><span class="db-health-pill {{ $healthClass($rowHealth['level']) }}">{{ strtoupper($rowHealth['level']) }}</span></td>
                                    <td><div class="flex justify-end gap-2">@if(!$row['isActive'])<form method="POST" action="{{ route('database-manager.activate', $row['school']->id) }}">@csrf<x-ui.button type="submit">Aktifkan</x-ui.button></form>@endif<form method="POST" action="{{ route('database-manager.migrate', $row['school']->id) }}">@csrf<x-ui.button type="submit" variant="secondary">Migrasi</x-ui.button></form></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div id="database-school-empty" class="hidden db-empty-state">Tidak ada sekolah yang cocok dengan pencarian.</div>
            </div>

            <div data-panel="tables" class="hidden p-0">
                @if(!$active['school'])
                    <div class="db-empty-state"><strong>Belum ada database aktif.</strong><span>Aktifkan sekolah sebelum membuka Explorer Tabel.</span></div>
                @elseif(!empty($tableError))
                    <div class="m-4"><x-ui.alert type="danger" title="Explorer tabel tidak dapat dibuka">{{ $tableError }}</x-ui.alert></div>
                @else
                    <div class="db-table-explorer lg:flex lg:min-h-[620px]">
                        <aside class="db-table-sidebar lg:w-[330px] lg:shrink-0">
                            <div class="db-table-sidebar-head">
                                <div class="flex items-center justify-between gap-2"><div><p class="db-eyebrow">Explorer</p><h3 class="font-bold text-[var(--ui-fg-strong)]">Daftar tabel</h3></div><span class="db-tab-count">{{ count($tables) }}</span></div>
                                <input id="table-search" type="search" placeholder="Cari tabel..." class="ui-input mt-3 w-full">
                                <p class="mt-2 truncate text-xs text-[var(--ui-fg-muted)]">{{ $active['school']->name }} · {{ basename($active['database']) }}</p>
                            </div>
                            <div class="db-table-sidebar-list">
                                <table class="w-full text-sm"><thead><tr><th data-sort="name">Nama <span class="sort-icon">↕</span></th><th data-sort="rows" class="text-right">Rows <span class="sort-icon">↕</span></th><th></th></tr></thead><tbody id="table-list-body">
                                    @foreach($tables as $t)
                                        <tr data-name="{{ strtolower($t['name']) }}" data-rows="{{ $t['count'] ?? 0 }}" class="{{ $table === $t['name'] ? 'is-active' : '' }}">
                                            <td><p class="truncate font-mono font-semibold">{{ $t['name'] }}</p><p class="truncate text-[11px] text-[var(--ui-fg-muted)]">{{ str($t['sql'])->limit(28) }}</p></td>
                                            <td class="text-right font-mono text-xs">{{ $t['count'] ?? '—' }}</td>
                                            <td class="text-right"><a href="{{ route('database-manager.index', ['table' => $t['name']]) }}#tables" class="ui-btn ui-btn-secondary !min-h-0 !px-2 !py-1 !text-xs">Buka</a></td>
                                        </tr>
                                    @endforeach
                                </tbody></table>
                            </div>
                            <div class="db-table-pagination"><span id="table-pagination-info"></span><div class="flex gap-1"><button id="table-prev" type="button" class="ui-btn ui-btn-secondary !min-h-0 !px-2 !py-1 !text-xs">‹</button><button id="table-next" type="button" class="ui-btn ui-btn-secondary !min-h-0 !px-2 !py-1 !text-xs">›</button></div></div>
                        </aside>

                        <div class="min-w-0 flex-1">
                            @if(!$table)
                                <div class="db-empty-state h-full min-h-[420px]"><span class="db-icon-tile">SQL</span><strong>Pilih tabel untuk diperiksa</strong><span>Schema dan data akan ditampilkan tanpa mengubah isi database.</span></div>
                            @else
                                <div class="db-table-detail-head">
                                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-mono text-lg font-bold text-[var(--ui-fg-strong)]">{{ $table }}</h3><span class="db-health-pill db-health-ok">{{ $tableData?->total() ?? collect($tables)->firstWhere('name', $table)['count'] ?? '—' }} rows</span>@if($schema)<span class="db-health-pill">{{ count($schema) }} kolom</span>@endif</div><p class="mt-1 truncate font-mono text-xs text-[var(--ui-fg-muted)]">{{ collect($tables)->firstWhere('name', $table)['sql'] ?? '' }}</p></div>
                                    <a href="{{ route('database-manager.index') }}#tables" class="ui-btn ui-btn-secondary !min-h-0 !py-1.5 !text-xs">Tutup</a>
                                </div>
                                <div class="db-inner-tabs"><button type="button" data-tm-tab="schema" class="tm-tab">Schema</button><button type="button" data-tm-tab="data" class="tm-tab">Data</button></div>
                                <div data-tm-panel="schema">
                                    @if($schema)
                                        <div class="overflow-x-auto"><table class="db-data-table min-w-[700px] w-full"><thead><tr><th>#</th><th>Nama kolom</th><th>Type</th><th>NN</th><th>PK</th><th>Default</th></tr></thead><tbody>@foreach($schema as $col)<tr><td class="font-mono text-xs">{{ $col->cid }}</td><td class="font-mono font-semibold">{{ $col->name }}</td><td><span class="db-code-chip">{{ $col->type }}</span></td><td>{{ $col->notnull ? 'Ya' : '—' }}</td><td>{{ $col->pk ? 'Ya' : '—' }}</td><td class="font-mono text-xs">{{ $col->dflt_value ?? '—' }}</td></tr>@endforeach</tbody></table></div>
                                    @endif
                                </div>
                                <div data-tm-panel="data" class="hidden">
                                    @if($tableData && $tableData->count())
                                        <div class="max-h-[460px] overflow-auto"><table class="db-data-table min-w-[760px] w-full"><thead class="sticky top-0"><tr>@foreach(array_keys((array) $tableData->first()) as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>@foreach($tableData as $row)<tr>@foreach((array) $row as $value)<td class="max-w-[260px] truncate font-mono text-xs" title="{{ is_string($value) ? $value : json_encode($value) }}">@if(is_null($value))<em>NULL</em>@elseif($value === '')—@else{{ str(is_string($value) ? $value : json_encode($value))->limit(90) }}@endif</td>@endforeach</tr>@endforeach</tbody></table></div>
                                        <div class="db-data-footer"><x-page-table-per-page :total="$tableData->total()" /><span>{{ $tableData->firstItem() }}–{{ $tableData->lastItem() }} dari {{ $tableData->total() }}</span><div>{{ $tableData->appends(['table' => $table, 'perPage' => request('perPage', 15)])->links('pagination::simple-tailwind') }}</div></div>
                                    @elseif($tableData)
                                        <div class="db-empty-state">Tabel ini belum memiliki data.</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div data-panel="diagnostic" class="hidden db-panel space-y-4">
                @if($activeStatus)
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="db-card">
                            <div class="db-card-header"><div><p class="db-eyebrow">Kesehatan file</p><h2 class="db-card-title">Diagnostik SQLite</h2></div><span class="db-health-pill {{ $integrityOk ? 'db-health-ok' : 'db-health-danger' }}">{{ $activeStatus['integrity'] ?? '—' }}</span></div>
                            <div class="db-diagnostic-list">
                                <div><span>File tersedia</span><strong>{{ $activeStatus['exists'] ? 'Ya' : 'Tidak' }}</strong></div>
                                <div><span>Writable</span><strong>{{ $activeStatus['isWritable'] ? 'Ya' : 'Tidak' }}</strong></div>
                                <div><span>Database</span><strong>{{ $fmtBytes($activeStatus['size']) }}</strong></div>
                                <div><span>WAL</span><strong>{{ $fmtBytes($activeStatus['walSize']) }}</strong></div>
                                <div><span>SHM</span><strong>{{ $fmtBytes($activeStatus['shmSize']) }}</strong></div>
                                <div><span>Status</span><strong>{{ $activeStatus['status'] ?? '—' }}</strong></div>
                            </div>
                            <div class="p-4 pt-0"><form method="POST" action="{{ route('database-manager.integrity', $activeStatus['school']->id) }}">@csrf<x-ui.button type="submit">Jalankan integrity check</x-ui.button></form></div>
                        </section>
                        <section class="db-card">
                            <div class="db-card-header"><div><p class="db-eyebrow">Teknis</p><h2 class="db-card-title">Koneksi & path</h2></div></div>
                            <div class="p-4 space-y-3">
                                <div class="db-path-box"><p class="db-eyebrow">Connection</p><p class="mt-1 font-mono text-xs">database.connections.school</p></div>
                                <div class="db-path-box"><p class="db-eyebrow">Database path</p><p class="mt-1 break-all font-mono text-xs">{{ $activeStatus['path'] }}</p></div>
                                @if($activeStatus['connectionError'])<x-ui.alert type="danger" title="Connection error">{{ $activeStatus['connectionError'] }}</x-ui.alert>@endif
                            </div>
                        </section>
                    </div>
                    <section class="db-card">
                        <div class="db-card-header"><div><p class="db-eyebrow">Record penting</p><h2 class="db-card-title">Table count yang dipantau</h2></div></div>
                        <div class="db-table-counts db-table-counts-wide">@foreach($activeStatus['tableCounts'] as $tableName => $count)<div><span class="font-mono">{{ $tableName }}</span><strong>{{ $count ?? 'error' }}</strong></div>@endforeach</div>
                    </section>
                @else
                    <div class="db-empty-state">Tidak ada database aktif untuk didiagnostik.</div>
                @endif
            </div>

            <div data-panel="maintenance" class="hidden db-panel space-y-4">
                @if($activeStatus)
                    <div class="grid gap-4 xl:grid-cols-3">
                        <section class="db-maintenance-card is-safe">
                            <p class="db-eyebrow">Rutin / aman</p><h2>Checkpoint WAL</h2><p>Flush WAL ke file database utama. Cocok dijalankan saat WAL membesar atau sebelum backup.</p><form method="POST" action="{{ route('database-manager.checkpoint', $activeStatus['school']->id) }}">@csrf<x-ui.button type="submit">Jalankan checkpoint</x-ui.button></form>
                        </section>
                        <section class="db-maintenance-card is-caution">
                            <p class="db-eyebrow">Struktur</p><h2>Migrasi database</h2><p>Jalankan seluruh migration school yang belum diterapkan pada tenant aktif.</p><form method="POST" action="{{ route('database-manager.migrate', $activeStatus['school']->id) }}">@csrf<x-ui.button type="submit">Jalankan migrasi</x-ui.button></form>
                        </section>
                        <section class="db-maintenance-card is-caution">
                            <p class="db-eyebrow">Optimasi</p><h2>VACUUM SQLite</h2><p>Reclaim space dan defragment file. Dapat mengunci database sementara.</p><form method="POST" action="{{ route('database-manager.vacuum', $activeStatus['school']->id) }}" data-confirm="VACUUM akan mengunci database sebentar. Pastikan tidak ada proses aktif dan backup tersedia. Lanjutkan?">@csrf<x-ui.button type="submit">Jalankan VACUUM</x-ui.button></form>
                        </section>
                    </div>
                    <section class="db-card">
                        <div class="db-card-header"><div><p class="db-eyebrow">Pemulihan struktur</p><h2 class="db-card-title">Provision & backup</h2></div></div>
                        <div class="grid gap-3 p-4 md:grid-cols-2">
                            <form method="POST" action="{{ route('database-manager.provision', $activeStatus['school']->id) }}" class="db-action-card" data-confirm="Provision akan membuat file bila hilang dan menjalankan migrasi. Lanjutkan?">@csrf<button type="submit"><strong>Provision database</strong><span>Gunakan bila file tenant belum ada atau perlu dibuat ulang strukturnya tanpa reset total.</span></button></form>
                            <a href="{{ route('school-backups.index') }}" class="db-action-card"><strong>Backup & Restore</strong><span>Buat backup sebelum migrasi besar, VACUUM, atau tindakan korektif.</span></a>
                        </div>
                    </section>
                    <section class="db-danger-zone">
                        <div><p class="db-eyebrow">Zona berbahaya</p><h2>Reset total database sekolah aktif</h2><p>Menghapus seluruh data tenant, SQLite sequence, WAL/SHM, lalu membangun kembali database. Hanya gunakan ketika benar-benar ingin memulai database sekolah dari nol.</p></div>
                        <x-ui.button :href="route('database-manager.reset-form')">Buka halaman reset</x-ui.button>
                    </section>
                @else
                    <div class="db-empty-state">Pilih sekolah aktif sebelum menjalankan maintenance.</div>
                @endif
            </div>
        </section>

        <section class="db-connection-footnote">
            <div><p class="db-eyebrow">Sumber koneksi</p><strong>SchoolDatabaseManager adalah sumber kebenaran koneksi tenant.</strong><p>Jangan mengubah <code>config/database.php</code> secara manual. Aktivasi sekolah memperbarui session dan koneksi <code>school</code> secara terpusat.</p></div>
            <code>{{ $active['database'] ?: 'database belum aktif' }}</code>
        </section>
    </div>

    <script>
        (() => {
            const root = document.getElementById('db-tabs');
            if (!root) return;

            const buttons = root.querySelectorAll('[data-tab]');
            const panels = root.querySelectorAll('[data-panel]');
            const storageKey = 'db-manager-tab';
            const validTabs = ['overview', 'list', 'tables', 'diagnostic', 'maintenance'];
            const setTab = (name) => {
                buttons.forEach(button => button.dataset.active = (button.dataset.tab === name).toString());
                panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.panel !== name));
                localStorage.setItem(storageKey, name);
                history.replaceState(null, '', '#'+name);
            };
            buttons.forEach(button => button.addEventListener('click', () => setTab(button.dataset.tab)));
            let initialTab = location.hash.replace('#', '') || localStorage.getItem(storageKey) || 'overview';
            if (new URLSearchParams(location.search).has('table')) initialTab = 'tables';
            setTab(validTabs.includes(initialTab) ? initialTab : 'overview');

            const schoolSearch = document.getElementById('database-school-search');
            const schoolRows = Array.from(document.querySelectorAll('#database-school-list tr'));
            const schoolEmpty = document.getElementById('database-school-empty');
            if (schoolSearch) {
                const filterSchools = () => {
                    const query = schoolSearch.value.toLowerCase().trim();
                    let visible = 0;
                    schoolRows.forEach(row => {
                        const match = !query || (row.dataset.search || '').includes(query);
                        row.hidden = !match;
                        if (match) visible++;
                    });
                    schoolEmpty?.classList.toggle('hidden', visible > 0);
                };
                schoolSearch.addEventListener('input', filterSchools);
            }

            const tableSearch = document.getElementById('table-search');
            const tableBody = document.getElementById('table-list-body');
            const previousButton = document.getElementById('table-prev');
            const nextButton = document.getElementById('table-next');
            const tableInfo = document.getElementById('table-pagination-info');
            if (tableBody) {
                const allRows = Array.from(tableBody.querySelectorAll('tr'));
                let filteredRows = [...allRows];
                let currentPage = 1;
                const perPage = 15;
                let sortKey = 'name';
                let sortDirection = 'asc';

                const sortRows = () => filteredRows.sort((a, b) => {
                    const aValue = sortKey === 'rows' ? parseInt(a.dataset.rows || '0', 10) : (a.dataset.name || '');
                    const bValue = sortKey === 'rows' ? parseInt(b.dataset.rows || '0', 10) : (b.dataset.name || '');
                    if (sortKey === 'name') return sortDirection === 'asc' ? String(aValue).localeCompare(String(bValue)) : String(bValue).localeCompare(String(aValue));
                    return sortDirection === 'asc' ? aValue - bValue : bValue - aValue;
                });

                const renderRows = () => {
                    allRows.forEach(row => row.style.display = 'none');
                    const total = filteredRows.length;
                    const pages = Math.max(1, Math.ceil(total / perPage));
                    currentPage = Math.min(Math.max(currentPage, 1), pages);
                    const start = (currentPage - 1) * perPage;
                    filteredRows.slice(start, start + perPage).forEach(row => row.style.display = '');
                    if (tableInfo) tableInfo.textContent = total ? `${start + 1}–${Math.min(start + perPage, total)} dari ${total}` : 'Tidak ada tabel';
                    if (previousButton) previousButton.disabled = currentPage <= 1;
                    if (nextButton) nextButton.disabled = currentPage >= pages;
                };

                const applyTableFilter = () => {
                    const query = (tableSearch?.value || '').toLowerCase().trim();
                    filteredRows = allRows.filter(row => !query || (row.dataset.name || '').includes(query));
                    currentPage = 1;
                    sortRows();
                    renderRows();
                };

                tableSearch?.addEventListener('input', applyTableFilter);
                root.querySelectorAll('th[data-sort]').forEach(header => header.addEventListener('click', () => {
                    const key = header.dataset.sort;
                    if (sortKey === key) sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                    else { sortKey = key; sortDirection = 'asc'; }
                    root.querySelectorAll('th[data-sort] .sort-icon').forEach(icon => icon.textContent = '↕');
                    header.querySelector('.sort-icon').textContent = sortDirection === 'asc' ? '↑' : '↓';
                    sortRows();
                    renderRows();
                }));
                previousButton?.addEventListener('click', () => { currentPage--; renderRows(); });
                nextButton?.addEventListener('click', () => { currentPage++; renderRows(); });
                applyTableFilter();
            }

            const innerButtons = root.querySelectorAll('.tm-tab');
            const innerPanels = root.querySelectorAll('[data-tm-panel]');
            const innerKey = 'tm-inner-tab';
            const setInnerTab = (name) => {
                innerButtons.forEach(button => button.dataset.active = (button.dataset.tmTab === name).toString());
                innerPanels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.tmPanel !== name));
                localStorage.setItem(innerKey, name);
            };
            innerButtons.forEach(button => button.addEventListener('click', () => setInnerTab(button.dataset.tmTab)));
            if (innerButtons.length) {
                let saved = localStorage.getItem(innerKey) || 'schema';
                if (!root.querySelector('[data-tm-panel="data"] table')) saved = 'schema';
                setInnerTab(['schema', 'data'].includes(saved) ? saved : 'schema');
            }
        })();
    </script>
</x-layouts.tailwind-app>
