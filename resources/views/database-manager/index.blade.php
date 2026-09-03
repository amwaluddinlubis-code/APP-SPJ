<x-layouts.tailwind-app>
    @php($fmtBytes = fn($b) => $b < 1024 ? $b.' B' : ($b < 1048576 ? number_format($b/1024,1).' KB' : number_format($b/1048576,2).' MB'))
    @php($badgeLevel = fn($level) => $level==='ok' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($level==='warning' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200'))
    <div class="space-y-5">
        {{-- Header --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <p class="text-xs font-bold tracking-[.16em] text-sky-200">MAINTENANCE · DATABASE AKTIF</p>
                <h1 class="mt-2 text-2xl font-bold text-white sm:text-3xl">Manajemen Database Aktif</h1>
                <p class="mt-1 text-base text-indigo-100">Satu modul untuk kelola koneksi <span class="font-mono font-bold">school</span> (SQLite per NPSN), migrasi, health check & maintenance. Mempermudah debug tanpa ubah <code>config/database.php:45</code> manual.</p>
            </div>
            <div class="grid gap-3 bg-slate-50/60 px-4 py-3 sm:grid-cols-3 text-base">
                <div class="rounded-lg border bg-white px-3 py-2.5">
                    <p class="text-[11px] font-bold uppercase text-slate-400">Koneksi Aktif</p>
                    <p class="mt-1 font-mono text-xs font-bold text-slate-800 truncate" title="{{ $active['database'] }}">{{ $active['database'] ? basename($active['database']) : '—' }}</p>
                    @if(!empty($active['isDummy']) || !$active['school'])
                        <p class="mt-0.5 text-xs text-amber-600">● Belum pilih sekolah — pakai dummy valid</p>
                    @else
                        <p class="mt-0.5 text-xs {{ $active['connected'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $active['connected'] ? '● Connected — '.($active['school']->name ?? '') : '● Error: '.str($active['error'])->limit(40) }}</p>
                    @endif
                </div>
                <div class="rounded-lg border bg-white px-3 py-2.5">
                    <p class="text-[11px] font-bold uppercase text-slate-400">Sekolah Aktif</p>
                    <p class="mt-1 text-base font-bold text-slate-800">{{ $active['school']?->name ?? '— Belum pilih —' }}</p>
                    <p class="text-xs text-slate-500">NPSN {{ $active['school']?->npsn ?? '-' }} · Session ID {{ $active['schoolId'] ?? '-' }}</p>
                </div>
                <div class="rounded-lg border bg-white px-3 py-2.5">
                    <p class="text-[11px] font-bold uppercase text-slate-400">Total Database</p>
                    <p class="mt-1 text-2xl font-bold text-slate-800">{{ $list->count() }}</p>
                    <p class="text-xs text-slate-500">{{ $list->filter(fn($s)=>$s['exists'])->count() }} file ada · {{ $list->filter(fn($s)=>$s['isActive'])->count() }} aktif</p>
                </div>
            </div>
        </section>

        {{-- Tabs --}}
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow" id="db-tabs">
            <div class="border-b border-slate-200 bg-slate-50/70">
                <nav class="flex gap-1 overflow-x-auto px-2 py-1 text-base">
                    <button data-tab="overview" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600">◎ Overview</button>
                    <button data-tab="list" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600">📚 Daftar Database</button>
                    <button data-tab="tables" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600">🗂️ Table Manager @if(!empty($tables))<span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px]">{{ count($tables) }}</span>@endif</button>
                    <button data-tab="diagnostic" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600">🔍 Diagnostik</button>
                    <button data-tab="maintenance" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600">🛠️ Maintenance</button>
                </nav>
            </div>

            {{-- Panel Overview --}}
            <div data-panel="overview" class="p-4 space-y-4">
                @if($activeStatus)
                    @php($h = app(\App\Services\SchoolDatabaseManager::class)->health($activeStatus['school']))
                    <div class="rounded-lg border p-3 flex items-center justify-between {{ $badgeLevel($h['level']) }}">
                        <div>
                            <p class="text-base font-bold">Health: {{ strtoupper($h['level']) }}</p>
                            <p class="text-xs mt-0.5">{{ empty($h['issues']) ? 'Semua OK' : implode(' · ', $h['issues']) }}</p>
                        </div>
                        <span class="text-xs font-mono">{{ $activeStatus['integrity'] ?? '-' }}</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-base">
                        <div class="rounded-lg border bg-slate-50 px-3 py-2.5"><p class="text-[11px] font-bold text-slate-400">FILE SIZE</p><p class="mt-1 font-bold">{{ $fmtBytes($activeStatus['totalSize']) }}</p><p class="text-xs text-slate-500">DB {{ $fmtBytes($activeStatus['size']) }} + WAL {{ $fmtBytes($activeStatus['walSize']) }}</p></div>
                        <div class="rounded-lg border bg-slate-50 px-3 py-2.5"><p class="text-[11px] font-bold text-slate-400">STATUS</p><p class="mt-1 font-bold">{{ $activeStatus['status'] ?? '-' }}</p><p class="text-xs text-slate-500">Migrated {{ $activeStatus['lastMigrated']?->diffForHumans() ?? '—' }}</p></div>
                        <div class="rounded-lg border bg-slate-50 px-3 py-2.5"><p class="text-[11px] font-bold text-slate-400">WRITABLE</p><p class="mt-1 font-bold {{ $activeStatus['isWritable'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $activeStatus['isWritable'] ? 'Yes' : 'No' }}</p><p class="text-xs text-slate-500 truncate">{{ $activeStatus['path'] }}</p></div>
                        <div class="rounded-lg border bg-slate-50 px-3 py-2.5"><p class="text-[11px] font-bold text-slate-400">TABLE COUNTS</p><div class="mt-1 space-y-0.5 text-xs">@foreach($activeStatus['tableCounts'] as $tbl=>$cnt)<div class="flex justify-between"><span class="text-slate-500">{{ $tbl }}</span><span class="font-bold">{{ $cnt ?? '—' }}</span></div>@endforeach</div></div>
                    </div>
                @else
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-base text-amber-800">Belum ada sekolah aktif. Pilih sekolah dulu di <a href="{{ route('schools.select') }}" class="font-bold underline">Pilih Sekolah</a> atau aktifkan dari tab Daftar.</div>
                @endif
                <div class="rounded-lg bg-slate-900 px-4 py-3 text-xs font-mono text-emerald-200 overflow-x-auto">
                    <p class="font-bold text-slate-400">Active connection (config/database.php:45)</p>
                    <p class="mt-1 break-all">database.connections.school.database = {{ $active['database'] }}</p>
                    <p class="mt-1 text-slate-500">Dipakai oleh Middleware EnsureActiveSchool + AppServiceProvider. Jangan edit manual, pakai tombol Aktifkan di modul ini.</p>
                </div>
            </div>

            {{-- Panel List --}}
            <div data-panel="list" class="hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-[800px] w-full text-base">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                            <tr><th class="px-3 py-2 text-left">Sekolah</th><th class="px-3 py-2">File</th><th class="px-3 py-2 text-right">Size</th><th class="px-3 py-2">Koneksi</th><th class="px-3 py-2">Health</th><th class="px-3 py-2 text-right">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($list as $row)
                                @php($health = app(\App\Services\SchoolDatabaseManager::class)->health($row['school']))
                                <tr class="{{ $row['isActive'] ? 'bg-indigo-50/40' : '' }}">
                                    <td class="px-3 py-2.5">
                                        <p class="font-bold text-slate-800">{{ $row['school']->name }} @if($row['isActive'])<span class="ml-1 rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] text-white">AKTIF</span>@endif</p>
                                        <p class="text-xs text-slate-500">NPSN {{ $row['school']->npsn }} · ID {{ $row['school']->id }}</p>
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-xs">{{ $row['exists'] ? '✓ '.basename($row['path']) : '✗ missing' }}</td>
                                    <td class="px-3 py-2.5 text-right text-xs">{{ $fmtBytes($row['totalSize']) }}</td>
                                    <td class="px-3 py-2.5 text-xs {{ $row['connectionOk'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $row['connectionOk'] ? 'OK' : 'ERR' }}</td>
                                    <td class="px-3 py-2.5"><span class="rounded-full border px-2 py-0.5 text-xs font-bold {{ $badgeLevel($health['level']) }}">{{ strtoupper($health['level']) }}</span></td>
                                    <td class="px-3 py-2.5 text-right">
                                        <div class="flex justify-end gap-1">
                                            @if(!$row['isActive'])<form method="POST" action="{{ route('database-manager.activate', $row['school']->id) }}">@csrf<button class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-indigo-700">Aktifkan</button></form>@endif
                                            <form method="POST" action="{{ route('database-manager.migrate', $row['school']->id) }}">@csrf<button class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-bold hover:bg-slate-50">Migrate</button></form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Panel Table Manager — Elegant Modern --}}
            <div data-panel="tables" class="hidden">
                @if(!$active['school'])
                    <div class="p-8 text-center">
                        <div class="mx-auto max-w-md rounded-xl border border-amber-200 bg-amber-50 p-6">
                            <p class="text-base font-bold text-amber-800">Belum ada database aktif</p>
                            <p class="mt-1 text-xs text-amber-700">Pilih sekolah di tab Daftar untuk mengaktifkan koneksi, lalu table manager akan menampilkan struktur.</p>
                            <button onclick="document.querySelector('[data-tab=list]').click()" class="mt-3 rounded-md bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700">Ke Daftar Database →</button>
                        </div>
                    </div>
                @elseif(!empty($tableError))
                    <div class="m-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-base text-rose-700">Error: {{ $tableError }}</div>
                @else
                    <div class="flex flex-col lg:flex-row lg:min-h-[560px]">
                        {{-- Sidebar — table list as sortable table --}}
                        <aside class="lg:w-[340px] lg:shrink-0 lg:border-r lg:bg-slate-50/20 flex flex-col">
                            <div class="p-3 border-b bg-white sticky top-0 z-10">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-xs font-bold tracking-wide text-slate-600 uppercase">Tables</h3>
                                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700">{{ count($tables) }} tabel</span>
                                </div>
                                <div class="relative mt-2">
                                    <input id="table-search" type="text" placeholder="Cari tabel..." class="w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 py-2 text-base placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 outline-none">
                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                                </div>
                                <p class="mt-1.5 text-[11px] text-slate-500 truncate">{{ $active['school']->name }} · <span class="font-mono">{{ basename($active['database']) }}</span></p>
                            </div>
                            <div class="flex-1 flex flex-col min-h-0">
                                <div class="overflow-auto flex-1">
                                    <table class="w-full text-base">
                                        <thead class="sticky top-0 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 z-[1]">
                                            <tr>
                                                <th data-sort="name" class="cursor-pointer select-none px-3 py-2 text-left hover:text-indigo-600 hover:bg-slate-100 transition">Nama Tabel <span class="sort-icon opacity-40">↕</span></th>
                                                <th data-sort="rows" class="cursor-pointer select-none px-3 py-2 text-right hover:text-indigo-600 hover:bg-slate-100 transition">Rows <span class="sort-icon opacity-40">↕</span></th>
                                                <th class="px-3 py-2 text-center w-[72px]">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="table-list-body" class="divide-y divide-slate-100 bg-white">
                                            @foreach($tables as $idx => $t)
                                                <tr data-name="{{ strtolower($t['name']) }}" data-rows="{{ $t['count'] ?? 0 }}" data-table-name="{{ $t['name'] }}" class="group hover:bg-indigo-50/40 transition {{ $table===$t['name'] ? '!bg-indigo-50 !border-l-2 !border-l-indigo-500' : '' }}">
                                                    <td class="px-3 py-2.5">
                                                        <p class="font-mono text-base font-semibold truncate {{ $table===$t['name'] ? 'text-indigo-700' : 'text-slate-800' }}">{{ $t['name'] }}</p>
                                                        <p class="text-[11px] text-slate-400 truncate hidden lg:block">{{ str($t['sql'])->limit(32) }}</p>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-right"><span class="inline-flex rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs {{ $table===$t['name'] ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600' }}">{{ $t['count'] ?? '—' }}</span></td>
                                                    <td class="px-3 py-2.5 text-center">
                                                        <a href="{{ route('database-manager.index', ['table' => $t['name']]) }}#tables" class="inline-flex rounded-md px-2.5 py-1 text-xs font-bold border transition {{ $table===$t['name'] ? 'bg-indigo-600 text-white border-indigo-600 shadow' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300' }}">Browse</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                {{-- Pagination ringan tanpa scroll panjang --}}
                                <div class="flex items-center justify-between border-t bg-white px-3 py-2 text-xs">
                                    <span id="table-pagination-info" class="text-slate-500"></span>
                                    <div class="flex gap-1">
                                        <button id="table-prev" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">‹ Prev</button>
                                        <button id="table-next" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-bold hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next ›</button>
                                    </div>
                                </div>
                            </div>
                        </aside>

                        {{-- Main — detail --}}
                        <div class="flex-1 min-w-0 bg-white flex flex-col">
                            @if(!$table)
                                <div class="flex-1 grid place-items-center p-8 text-center">
                                    <div class="max-w-sm">
                                        <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-indigo-50 text-indigo-600">🗂️</div>
                                        <h4 class="mt-3 text-base font-bold text-slate-800">Pilih tabel di samping</h4>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Daftar di kiri berisi semua tabel SQLite aktif. Klik untuk melihat <b>schema</b> & <b>15 baris data</b> secara live tanpa reload halaman penuh.</p>
                                        <div class="mt-4 flex flex-wrap justify-center gap-1.5">
                                            @foreach(collect($tables)->take(4) as $t)
                                                <a href="{{ route('database-manager.index', ['table' => $t['name']]) }}#tables" class="rounded-full border bg-white px-2.5 py-1 text-xs font-mono hover:bg-slate-50">{{ $t['name'] }}</a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                {{-- Header --}}
                                <div class="border-b bg-gradient-to-r from-slate-50 to-white px-4 py-3 sm:px-5">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-mono text-base font-bold text-slate-800">{{ $table }}</h4>
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200">{{ $tableData?->total() ?? $tables[array_search($table, array_column($tables,'name'))]['count'] ?? '—' }} rows</span>
                                                @if($schema)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">{{ count($schema) }} cols</span>@endif
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500 line-clamp-1 font-mono">{{ collect($tables)->firstWhere('name',$table)['sql'] ?? '' }}</p>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ route('database-manager.index') }}#tables" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">✕ Tutup</a>
                                        </div>
                                    </div>
                                    {{-- Tabs Schema / Data --}}
                                    <div class="mt-3 flex gap-1">
                                        <button data-tm-tab="schema" class="tm-tab whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-bold border data-[active=true]:bg-indigo-600 data-[active=true]:text-white data-[active=true]:border-indigo-600 data-[active=false]:bg-white data-[active=false]:border-slate-200 data-[active=false]:text-slate-600">Schema</button>
                                        <button data-tm-tab="data" class="tm-tab whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-bold border data-[active=true]:bg-indigo-600 data-[active=true]:text-white data-[active=true]:border-indigo-600 data-[active=false]:bg-white data-[active=false]:border-slate-200 data-[active=false]:text-slate-600">Data</button>
                                    </div>
                                </div>

                                {{-- Schema Panel --}}
                                <div data-tm-panel="schema" class="p-0">
                                    @if($schema)
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-xs">
                                                <thead class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wide text-slate-500 sticky top-0">
                                                    <tr><th class="px-3 py-2 text-left font-semibold">COL</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-center">NN</th><th class="px-3 py-2 text-center">PK</th><th class="px-3 py-2 text-left">Default</th></tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 bg-white">
                                                    @foreach($schema as $col)
                                                        <tr class="hover:bg-indigo-50/30 transition">
                                                            <td class="px-3 py-2 font-mono text-slate-400">{{ $col->cid }}</td>
                                                            <td class="px-3 py-2 font-mono font-semibold text-slate-800">{{ $col->name }} @if($col->pk)<span class="ml-1 rounded bg-amber-100 px-1 py-0.5 text-[10px] text-amber-700">PK</span>@endif</td>
                                                            <td class="px-3 py-2"><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px]">{{ $col->type }}</span></td>
                                                            <td class="px-3 py-2 text-center">{{ $col->notnull ? '●' : '—' }}</td>
                                                            <td class="px-3 py-2 text-center">{{ $col->pk ? '★' : '—' }}</td>
                                                            <td class="px-3 py-2 font-mono text-slate-500 text-[11px]">{{ $col->dflt_value ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>

                                {{-- Data Panel --}}
                                <div data-tm-panel="data" class="hidden flex flex-col">
                                    @if($tableData && $tableData->count())
                                        <div class="overflow-auto max-h-[360px] lg:max-h-[420px] border-t">
                                            <table class="min-w-[640px] w-full text-xs">
                                                <thead class="bg-slate-900 text-slate-100 sticky top-0">
                                                    <tr>@foreach(array_keys((array)$tableData->first()) as $h)<th class="px-3 py-2 text-left font-bold whitespace-nowrap tracking-wide text-[11px] uppercase">{{ $h }}</th>@endforeach</tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 bg-white">
                                                    @foreach($tableData as $idx => $row)
                                                        <tr class="hover:bg-indigo-50/40 transition {{ $idx%2===0 ? 'bg-white' : 'bg-slate-50/30' }}">
                                                            @foreach((array)$row as $v)
                                                                <td class="px-3 py-2 max-w-[240px] truncate font-mono text-slate-700" title="{{ is_string($v) ? $v : json_encode($v) }}">
                                                                    @if(is_null($v))<span class="text-slate-400 italic">NULL</span>@elseif($v==='' )<span class="text-slate-400">—</span>@else{{ str(is_string($v) ? $v : json_encode($v))->limit(90) }}@endif
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="border-t bg-slate-50 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                                            <div class="flex items-center gap-2">
                                                <x-page-table-per-page :total="$tableData->total()" />
                                                <span class="hidden sm:inline text-slate-400">•</span>
                                                <span class="text-slate-500">Menampilkan {{ $tableData->firstItem() }}–{{ $tableData->lastItem() }} dari {{ $tableData->total() }}</span>
                                            </div>
                                            <div class="flex gap-1">{{ $tableData->appends(['table'=>$table, 'perPage'=>request('perPage',15)])->links('pagination::simple-tailwind') }}</div>
                                        </div>
                                    @elseif($tableData)
                                        <div class="p-10 text-center">
                                            <p class="text-base font-medium text-slate-600">Tabel kosong</p>
                                            <p class="mt-1 text-xs text-slate-400">Belum ada baris di {{ $table }}.</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Panel Diagnostic --}}
            <div data-panel="diagnostic" class="hidden p-4 space-y-3">
                @if($activeStatus)
                    <h3 class="text-base font-bold text-slate-800">Diagnostik: {{ $activeStatus['school']->name }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border p-3">
                            <p class="text-xs font-bold text-slate-600">Integrity Check (PRAGMA)</p>
                            <p class="mt-1 font-mono text-base {{ strtolower($activeStatus['integrity'])==='ok' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $activeStatus['integrity'] }}</p>
                            <form method="POST" action="{{ route('database-manager.integrity', $activeStatus['school']->id) }}" class="mt-2">@csrf<button class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-bold hover:bg-slate-50">Run Check</button></form>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-xs font-bold text-slate-600">Table Counts (school connection)</p>
                            <div class="mt-2 space-y-1 text-xs">@foreach($activeStatus['tableCounts'] as $tbl=>$cnt)<div class="flex justify-between border-b border-slate-100 py-1"><span class="font-mono">{{ $tbl }}</span><span class="font-bold">{{ $cnt ?? 'error' }}</span></div>@endforeach</div>
                        </div>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3 text-xs">
                        <p class="font-bold text-slate-700">Path & Permissions</p>
                        <p class="mt-1 font-mono break-all">{{ $activeStatus['path'] }}</p>
                        <p class="mt-1">Exists: {{ $activeStatus['exists'] ? 'yes' : 'no' }} · Writable: {{ $activeStatus['isWritable'] ? 'yes' : 'no' }} · WAL {{ $fmtBytes($activeStatus['walSize']) }} · SHM {{ $fmtBytes($activeStatus['shmSize']) }}</p>
                        @if($activeStatus['connectionError'])<p class="mt-1 text-rose-700">Connection error: {{ $activeStatus['connectionError'] }}</p>@endif
                    </div>
                @else
                    <p class="text-base text-slate-500">Tidak ada database aktif untuk didiagnosa.</p>
                @endif
            </div>

            {{-- Panel Maintenance --}}
            <div data-panel="maintenance" class="hidden p-4 space-y-3">
                @if($activeStatus)
                    <h3 class="text-base font-bold text-slate-800">Maintenance: {{ $activeStatus['school']->name }}</h3>
                    <p class="text-xs text-slate-500">Jalankan hanya saat perlu. Backup otomatis disarankan sebelum VACUUM/migrasi.</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <form method="POST" action="{{ route('database-manager.migrate', $activeStatus['school']->id) }}" class="rounded-lg border p-3 bg-white">@csrf<button class="w-full rounded-md bg-indigo-600 px-3 py-1.5 text-base font-bold text-white hover:bg-indigo-700">▶ Migrate</button><p class="mt-1 text-xs text-slate-500">Jalankan <code>migrate --database=school</code> + update last_migrated_at.</p></form>
                        <form method="POST" action="{{ route('database-manager.checkpoint', $activeStatus['school']->id) }}" class="rounded-lg border p-3 bg-white">@csrf<button class="w-full rounded-md bg-emerald-600 px-3 py-1.5 text-base font-bold text-white hover:bg-emerald-700">⬢ Checkpoint WAL</button><p class="mt-1 text-xs text-slate-500">PRAGMA wal_checkpoint(FULL) untuk flush WAL ke DB.</p></form>
                        <form method="POST" action="{{ route('database-manager.vacuum', $activeStatus['school']->id) }}" class="rounded-lg border p-3 bg-white" data-confirm="VACUUM akan mengunci database sebentar. Lanjutkan?">@csrf<button class="w-full rounded-md bg-amber-600 px-3 py-1.5 text-base font-bold text-white hover:bg-amber-700">♻ Vacuum</button><p class="mt-1 text-xs text-slate-500">Reclaim space & defragment SQLite.</p></form>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('database-manager.provision', $activeStatus['school']->id) }}" data-confirm="Provision akan membuat file jika hilang dan menjalankan migrasi. Lanjutkan?">@csrf<button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold hover:bg-slate-50">Provision Ulang</button></form>
                        <a href="{{ route('school-backups.index') }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold hover:bg-slate-50">Ke Backup & Restore →</a>
                    </div>
                @else
                    <p class="text-base text-slate-500">Pilih sekolah aktif dulu.</p>
                @endif
                <div class="rounded-lg bg-slate-900 p-3 text-xs font-mono text-slate-300">
                    <p class="font-bold text-white">Tips Maintenance</p>
                    <p class="mt-1">• Service terpusat di <code>app/Services/SchoolDatabaseManager.php:1</code> - semua controller/middleware harus pakai ini, jangan Config::set manual.</p>
                    <p>• Tambah sekolah baru → auto provision via <code>SchoolConfigurationController:61</code>.</p>
                    <p>• Ganti DB aktif → <code>POST /pengaturan/database-aktif/{id}/activate</code> (update session + reconnect).</p>
                    <p>• Cron: jalankan checkpoint harian untuk hindari WAL bengkak.</p>
                </div>
            </div>
        </section>

        <p class="text-xs text-slate-500">Modul ini menggantikan cek manual di <code>SchoolConfigurationController.php:12</code> & <code>AppServiceProvider.php:26</code> agar 1 sumber kebenaran untuk koneksi <code>school</code>.</p>
    </div>

    <script>
        (() => {
            const btns = document.querySelectorAll('#db-tabs [data-tab]');
            const panels = document.querySelectorAll('#db-tabs [data-panel]');
            const key = 'db-manager-tab';
            const set = (name) => {
                btns.forEach(b => b.dataset.active = (b.dataset.tab===name).toString());
                panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel!==name));
                localStorage.setItem(key, name);
                history.replaceState(null,'','#'+name);
            };
            btns.forEach(b=>b.addEventListener('click',()=>set(b.dataset.tab)));
            let init = location.hash.replace('#','') || localStorage.getItem(key) || 'overview';
            const params = new URLSearchParams(location.search);
            if (params.has('table')) init = 'tables';
            set(['overview','list','tables','diagnostic','maintenance'].includes(init)?init:'overview');

            // Table Manager: sortable table + search + pagination (light, no reload, tidak perlu scroll panjang)
            const search = document.getElementById('table-search');
            const tbody = document.getElementById('table-list-body');
            const prevBtn = document.getElementById('table-prev');
            const nextBtn = document.getElementById('table-next');
            const infoEl = document.getElementById('table-pagination-info');
            if (tbody) {
                const allRows = Array.from(tbody.querySelectorAll('tr'));
                let filtered = [...allRows];
                let currentPage = 1;
                const perPage = 15;
                let sortKey = null;
                let sortDir = 'asc';

                const render = () => {
                    allRows.forEach(r => r.style.display = 'none');
                    const total = filtered.length;
                    const totalPages = Math.max(1, Math.ceil(total / perPage));
                    if (currentPage > totalPages) currentPage = totalPages;
                    if (currentPage < 1) currentPage = 1;
                    const start = (currentPage - 1) * perPage;
                    const pageRows = filtered.slice(start, start + perPage);
                    pageRows.forEach(r => r.style.display = '');
                    if (infoEl) infoEl.textContent = total ? `${start+1}–${Math.min(start+perPage, total)} dari ${total}` : 'Tidak ada tabel';
                    if (prevBtn) prevBtn.disabled = currentPage <= 1;
                    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
                };

                const applySearch = () => {
                    const q = (search?.value || '').toLowerCase().trim();
                    filtered = allRows.filter(r => {
                        const name = (r.dataset.name || '').toLowerCase();
                        return !q || name.includes(q);
                    });
                    // keep current sort
                    if (sortKey) {
                        filtered.sort((a,b) => {
                            let va = sortKey==='rows' ? parseInt(a.dataset.rows||0) : a.dataset.name;
                            let vb = sortKey==='rows' ? parseInt(b.dataset.rows||0) : b.dataset.name;
                            if (sortKey==='name') { va = String(va); vb = String(vb); return sortDir==='asc' ? va.localeCompare(vb) : vb.localeCompare(va); }
                            return sortDir==='asc' ? va - vb : vb - va;
                        });
                    }
                    currentPage = 1;
                    render();
                };

                if (search) search.addEventListener('input', applySearch);

                // sorting by header
                document.querySelectorAll('th[data-sort]').forEach(th => {
                    th.addEventListener('click', () => {
                        const key = th.dataset.sort;
                        if (sortKey === key) sortDir = sortDir==='asc' ? 'desc' : 'asc';
                        else { sortKey = key; sortDir = 'asc'; }
                        document.querySelectorAll('th[data-sort] .sort-icon').forEach(i=>i.textContent='↕');
                        th.querySelector('.sort-icon').textContent = sortDir==='asc' ? '↑' : '↓';
                        filtered.sort((a,b)=>{
                            let va = key==='rows' ? parseInt(a.dataset.rows||0) : a.dataset.name;
                            let vb = key==='rows' ? parseInt(b.dataset.rows||0) : b.dataset.name;
                            if (key==='name') return sortDir==='asc' ? String(va).localeCompare(String(vb)) : String(vb).localeCompare(String(va));
                            return sortDir==='asc' ? va - vb : vb - va;
                        });
                        render();
                    });
                });

                if (prevBtn) prevBtn.addEventListener('click', ()=>{ currentPage--; render(); });
                if (nextBtn) nextBtn.addEventListener('click', ()=>{ currentPage++; render(); });

                applySearch();
                render();
            }
            // Table Manager: inner tabs Schema/Data (dynamic, no reload)
            const tmBtns = document.querySelectorAll('.tm-tab');
            const tmPanels = document.querySelectorAll('[data-tm-panel]');
            const tmKey = 'tm-inner-tab';
            const setTm = (name) => {
                tmBtns.forEach(b => b.dataset.active = (b.dataset.tmTab===name).toString());
                tmPanels.forEach(p => p.classList.toggle('hidden', p.dataset.tmPanel!==name));
                localStorage.setItem(tmKey, name);
            };
            tmBtns.forEach(b=>b.addEventListener('click',()=>setTm(b.dataset.tmTab)));
            if (tmBtns.length) {
                const saved = localStorage.getItem(tmKey) || 'schema';
                setTm(['schema','data'].includes(saved) ? saved : 'schema');
                // jika data kosong, paksa ke schema
                const hasData = document.querySelector('[data-tm-panel=data] table');
                if (!hasData) setTm('schema');
            }
        })();
    </script>
</x-layouts.tailwind-app>
