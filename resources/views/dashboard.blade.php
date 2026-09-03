<x-layouts.tailwind-app>
    @php
        $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $colors = ['bg-indigo-500', 'bg-sky-500', 'bg-emerald-500', 'bg-amber-500', 'bg-violet-500', 'bg-rose-500'];
        $chartCategories = $categories->map(fn($c)=>['label'=>$c->label,'amount'=>(float)$c->amount])->values();
        $chartPayload = json_encode(['budget'=>(float)$budget,'realization'=>(float)$realization,'remaining'=>max(0,(float)$remaining),'categories'=>$chartCategories], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        $hierarchyPayload = json_encode($hierarchyChart->map(fn($h)=>['code'=>$h->account_code,'name'=>$h->account_name,'budget'=>(float)$h->budget,'realization'=>(float)$h->realization,'level'=>(int)$h->level])->values(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    @endphp
    <div class="space-y-6">
        {{-- Hero — theme --}}
        <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white shadow-sm sm:px-7 lg:py-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-bold tracking-[0.16em] text-white/70">DASHBOARD SPJ BOSP</p>
                    <h1 class="mt-2 text-2xl font-bold sm:text-3xl">{{ $school?->name ?? 'Sekolah belum dipilih' }}</h1>
                    <p class="mt-1 text-base text-white/80">Tahun Anggaran {{ $year->year }} · {{ $year->fund_source }} @if($school?->npsn) · NPSN {{ $school->npsn }} @endif</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-white/15 px-2.5 py-1 ring-1 ring-white/20">{{ number_format($transactionCount,0,',','.') }} transaksi</span><span class="rounded-full bg-white/15 px-2.5 py-1 ring-1 ring-white/20">{{ $hierarchyTotalCount }} kelompok rekening</span></div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('rkas-budget.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-base font-bold text-slate-800 shadow hover:bg-slate-50">Penganggaran RKAS</a>
                    <form method="POST" action="{{ route('arkas.sync') }}" data-confirm="Sinkronisasi akan memperbarui data RKAS dan BKU dari ARKAS. Lanjutkan?">@csrf<input type="hidden" name="confirm_sync" value="1"><button class="rounded-xl theme-btn px-4 py-2.5 text-base font-bold">Sinkron Semua ARKAS</button></form>
                </div>
            </div>
            <div class="mt-6 grid gap-3 border-t border-white/15 pt-4 sm:grid-cols-3">
                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-white/70">Status sinkronisasi</p><p class="mt-1 font-semibold">{{ $latestSync?->status === 'SUCCESS' ? 'Berhasil' : ($latestSync?->status ?? 'Belum pernah') }}</p></div>
                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-white/70">Sinkronisasi terakhir</p><p class="mt-1 font-semibold">{{ $latestSync?->finished_at ? \Illuminate\Support\Carbon::parse($latestSync->finished_at)->translatedFormat('d F Y H:i') : '—' }}</p></div>
                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15"><p class="text-xs text-white/70">Daya serap</p><p class="mt-1 font-semibold">{{ number_format($absorption,1,',','.') }}% · {{ $rupiah($realization) }}</p></div>
            </div>
        </section>

        @if($latestOperation && in_array($latestOperation->status, ['QUEUED', 'RUNNING', 'FAILED'], true))
            <section class="rounded-2xl border px-5 py-4 {{ $latestOperation->status === 'FAILED' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-sky-200 bg-sky-50 text-sky-800' }}">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="font-bold">Proses latar belakang: {{ $latestOperation->status }}</p><p class="mt-1 text-sm">{{ $latestOperation->message }}</p></div><span class="rounded-full bg-white px-3 py-1 text-sm font-bold">{{ $latestOperation->progress }}%</span></div>
            </section>
        @endif

        {{-- Metrics 4 — hover elegant --}}
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow hover:shadow-md hover:-translate-y-0.5 transition"><div class="flex items-center justify-between"><p class="text-xs font-bold tracking-wide text-slate-400">PAGU RKAS</p><span class="grid h-8 w-8 place-items-center rounded-lg theme-bg-soft theme-text">◈</span></div><p class="mt-3 text-2xl font-bold text-slate-900">{{ $rupiah($budget) }}</p><div class="mt-3 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full theme-bg" style="width:100%"></div></div><p class="mt-2 text-xs text-slate-500">Total anggaran tersinkron</p></article>
            <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow hover:shadow-md hover:-translate-y-0.5 transition"><div class="flex items-center justify-between"><p class="text-xs font-bold tracking-wide text-slate-400">REALISASI BKU</p><span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600">✓</span></div><p class="mt-3 text-2xl font-bold text-emerald-700">{{ $rupiah($realization) }}</p><div class="mt-3 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-500 transition-all duration-700" style="width: {{ min(100,$absorption) }}%"></div></div><p class="mt-2 text-xs text-slate-500">{{ number_format($absorption,1,',','.') }}% penyerapan</p></article>
            <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow hover:shadow-md hover:-translate-y-0.5 transition"><div class="flex items-center justify-between"><p class="text-xs font-bold tracking-wide text-slate-400">SISA ANGGARAN</p><span class="grid h-8 w-8 place-items-center rounded-lg bg-sky-50 text-sky-600">◍</span></div><p class="mt-3 text-2xl font-bold {{ $remaining<0?'text-rose-600':'text-sky-700' }}">{{ $rupiah($remaining) }}</p><p class="mt-2 text-xs {{ $remaining<0?'text-rose-500':'text-slate-500' }}">{{ $remaining<0?'Realisasi melebihi pagu':'Belum direalisasikan' }}</p></article>
            <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow hover:shadow-md hover:-translate-y-0.5 transition"><div class="flex items-center justify-between"><p class="text-xs font-bold tracking-wide text-slate-400">PAJAK TERCATAT</p><span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-50 text-amber-600">₿</span></div><p class="mt-3 text-2xl font-bold text-amber-600">{{ $rupiah($taxes) }}</p><p class="mt-2 text-xs text-slate-500">PPN, PPh, SSPD</p></article>
        </section>

        {{-- Row: Penyerapan + Komposisi --}}
        <section class="grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
            <article class="rounded-2xl border border-slate-200 bg-white shadow"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Penyerapan Anggaran</h2><p class="mt-1 text-base text-slate-500">Realisasi BKU terhadap pagu RKAS aktif.</p></div><div class="p-5"><div class="flex items-end justify-between"><div><p class="text-3xl font-bold text-slate-900">{{ number_format($absorption,1,',','.') }}%</p><p class="mt-1 text-base text-slate-500">{{ $rupiah($realization) }} dari {{ $rupiah($budget) }}</p></div><span class="rounded-full theme-bg-soft px-3 py-1 text-xs font-bold theme-text">TA {{ $year->year }}</span></div><div class="mt-5 h-4 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full theme-bg transition-all duration-700" style="width: {{ min(100,$absorption) }}%"></div></div><div class="mt-3 flex justify-between text-xs font-medium text-slate-500"><span>Realisasi</span><span>Sisa: {{ $rupiah($remaining) }}</span></div><div class="mt-6 h-52 sm:h-64"><canvas id="budget-progress-chart"></canvas></div></div></article>
            <article class="rounded-2xl border border-slate-200 bg-white shadow"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Komposisi Belanja</h2><p class="mt-1 text-base text-slate-500">Berdasarkan kategori SPJ.</p></div><div class="p-5"><div class="mx-auto h-52 sm:h-64 max-w-xs"><canvas id="spending-composition-chart"></canvas></div><div class="mt-5 space-y-3">@forelse($categories as $i=>$c)<div class="flex items-center justify-between gap-3 text-base"><span class="flex min-w-0 items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $colors[$i%count($colors)] }}"></span><span class="truncate font-medium text-slate-700">{{ $c->label }}</span></span><span class="shrink-0 font-semibold text-slate-800">{{ $rupiah($c->amount) }} <span class="text-xs text-slate-400">{{ number_format($c->percentage,1) }}%</span></span></div>@empty<p class="py-5 text-center text-base text-slate-500">Belum ada transaksi.</p>@endforelse</div></div></article>
        </section>

        {{-- Versi lama yang memuat Livewire RKAS di dashboard disimpan sementara.
             Rincian lengkap tetap tersedia pada menu Penganggaran RKAS. --}}
        @if(false)
        {{-- Gabungan Hierarki + RKAS dalam Tabs agar tidak berantakan --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow" x-data="{tab: Alpine.$persist('hierarki')}" id="dashboard-tabs">
            <div class="border-b border-slate-200 bg-slate-50/70 flex flex-wrap items-center justify-between gap-2 px-2 py-1">
                <nav class="flex gap-1 overflow-x-auto">
                    <button @click="tab='hierarki'" :data-active="tab==='hierarki'" class="whitespace-nowrap rounded-lg px-3 py-2 text-base font-bold border data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:theme-text data-[active=true]:shadow-sm data-[active=false]:border-transparent text-slate-600">📊 Hierarki Rekening L1–4</button>
                    <button @click="tab='rkas'" :data-active="tab==='rkas'" class="whitespace-nowrap rounded-lg px-3 py-2 text-base font-bold border data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:theme-text data-[active=true]:shadow-sm data-[active=false]:border-transparent text-slate-600">📋 Rincian RKAS Tanpa Triwulan</button>
                </nav>
                <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500 pr-2"><span class="rounded-full theme-bg-soft px-2 py-0.5 theme-text">15 dari {{ $hierarchyTotalCount }} hierarki</span><span>•</span><span>RKAS dimuat saat dibuka</span></div>
            </div>

            <div x-show="tab==='hierarki'" x-transition>
                {{-- Grafik --}}
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white">
                    <div><h3 class="font-bold text-slate-800">Perbandingan Realisasi vs Pagu</h3><p class="text-base text-slate-500">Enam kelompok dengan pagu terbesar dari hierarki level 1–4.</p></div>
                </div>
                <div class="p-5 bg-white">
                    @if($hierarchyChart->isEmpty())
                        <div class="rounded-xl border border-dashed p-8 text-center text-slate-500">Belum ada hierarki.</div>
                    @else
                        <div class="h-[300px] sm:h-[340px]"><canvas id="hierarchy-bar-chart"></canvas></div>
                        <p class="mt-2 text-xs text-slate-400 text-center">Batang ganda: Pagu vs Realisasi</p>
                    @endif
                </div>
                {{-- Tabel hierarki --}}
                <div class="border-t border-slate-200" id="hierarchy-table-section">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-3 bg-slate-50/50 border-b">
                        <p class="text-base font-bold text-slate-700">Rincian Per Rekening Hierarki</p>
                        <div class="flex items-center gap-2">
                            <input id="hierarchy-search" placeholder="Cari kode/nama..." class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-base placeholder:text-slate-400 focus:border-[var(--theme-accent)] focus:ring-2 focus:ring-[var(--theme-accent)]/20 outline-none w-48">
                            <span class="hidden sm:inline rounded-full theme-bg-soft px-2 py-0.5 text-xs theme-text">{{ $hierarchyComparison->count() }} baris</span>
                        </div>
                    </div>
                    <div class="grid gap-3 p-4 lg:hidden" id="hierarchy-cards">
                        @forelse($hierarchyComparison as $h)
                            <article data-code="{{ strtolower($h->account_code) }}" data-name="{{ strtolower($h->account_name) }}" data-budget="{{ $h->budget }}" data-real="{{ $h->realization }}" data-presentasi="{{ $h->shareBudget }}" data-level="{{ $h->level }}" class="h-card rounded-xl border border-slate-200 bg-white p-4 shadow hover:shadow transition">
                                <div class="flex items-start justify-between gap-2">
                                    <div><div class="flex items-center gap-1"><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs font-bold">L{{ $h->level }}</span><p class="font-mono text-base font-bold theme-text">{{ $h->account_code }}</p><span class="rounded-full theme-bg-soft px-1.5 py-0.5 text-xs theme-text">{{ number_format($h->shareBudget,1) }}% total pagu</span></div><p class="text-base font-semibold text-slate-800">{{ $h->account_name }}</p></div>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold border {{ $h->absorption>=80 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($h->absorption>=50 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-100 text-slate-600') }}">{{ number_format($h->absorption,1) }}%</span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-base">
                                    <div class="rounded-lg bg-slate-50 p-2"><p class="text-xs text-slate-400">Pagu</p><p class="font-bold text-slate-800">{{ $rupiah($h->budget) }}</p></div>
                                    <div class="rounded-lg bg-emerald-50 p-2"><p class="text-xs text-emerald-700">Realisasi</p><p class="font-bold text-emerald-700">{{ $rupiah($h->realization) }}</p></div>
                                </div>
                                <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full theme-bg" style="width: {{ min(100,$h->absorption) }}%"></div></div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed p-8 text-center text-slate-500">Belum ada data hierarki.</div>
                        @endforelse
                    </div>
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-[980px] w-full text-base" id="hierarchy-table">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th data-sort="level" class="cursor-pointer select-none px-3 py-3 text-center text-xs font-bold text-slate-500 hover:text-[var(--theme-accent)]">Lv <span class="sort-icon opacity-40">↕</span></th>
                                    <th data-sort="code" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-slate-500 hover:text-[var(--theme-accent)]">Kode <span class="sort-icon opacity-40">↕</span></th>
                                    <th data-sort="name" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-slate-500 hover:text-[var(--theme-accent)]">Nama <span class="sort-icon opacity-40">↕</span></th>
                                    <th data-sort="budget" class="cursor-pointer select-none px-4 py-3 text-right text-xs font-bold text-slate-500 hover:text-[var(--theme-accent)]">Pagu <span class="sort-icon opacity-40">↕</span></th>
                                    <th data-sort="real" class="cursor-pointer select-none px-4 py-3 text-right text-xs font-bold text-slate-500 hover:text-[var(--theme-accent)]">Realisasi <span class="sort-icon opacity-40">↕</span></th>
                                    <th data-sort="serap" class="cursor-pointer select-none px-4 py-3 text-right text-xs font-bold text-slate-500 hover:text-[var(--theme-accent)]">Serap% <span class="sort-icon opacity-40">↕</span></th>
                                    <th data-sort="presentasi" class="cursor-pointer select-none px-4 py-3 text-right text-xs font-bold text-slate-500 hover:text-[var(--theme-accent)]">Presentasi <span class="sort-icon opacity-40">↕</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" id="hierarchy-tbody">
                                @forelse($hierarchyComparison as $h)
                                    <tr data-code="{{ strtolower($h->account_code) }}" data-name="{{ strtolower($h->account_name) }}" data-budget="{{ $h->budget }}" data-real="{{ $h->realization }}" data-serap="{{ $h->absorption }}" data-presentasi="{{ $h->shareBudget }}" data-level="{{ $h->level }}" class="hover:bg-[color-mix(in_srgb,var(--theme-accent)_6%,white)] transition">
                                        <td class="px-3 py-3 text-center"><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs font-bold">L{{ $h->level }}</span></td>
                                        <td class="px-4 py-3 font-mono text-base font-bold theme-text">{{ $h->account_code }}</td>
                                        <td class="px-4 py-3 max-w-xs truncate" title="{{ $h->account_name }}">{{ $h->account_name }}</td>
                                        <td class="px-4 py-3 text-right font-semibold">{{ $rupiah($h->budget) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-emerald-700">{{ $rupiah($h->realization) }}</td>
                                        <td class="px-4 py-3 text-right"><span class="rounded-full px-2 py-0.5 text-xs font-bold border {{ $h->absorption>=80?'bg-emerald-50 text-emerald-700 border-emerald-200':($h->absorption>=50?'bg-amber-50 text-amber-700 border-amber-200':'bg-slate-100 text-slate-600') }}">{{ number_format($h->absorption,1) }}%</span></td>
                                        <td class="px-4 py-3 text-right"><span class="rounded-full theme-bg-soft px-2 py-0.5 text-xs font-bold theme-text border">{{ number_format($h->shareBudget,1) }}%</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">Belum ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t bg-slate-50/50 px-5 py-3 flex items-center justify-between text-base">
                        <span class="text-slate-500">Menampilkan <b>{{ $hierarchyComparison->count() }}</b> dari {{ $hierarchyTotalCount }} kelompok • Pagu {{ $rupiah($hierarchyTotals->budget) }} vs Realisasi {{ $rupiah($hierarchyTotals->realization) }}</span>
                        <a href="{{ route('synced-data.show','rekening') }}" class="text-base font-bold theme-text hover:underline">Lihat rekening →</a>
                    </div>
                </div>
            </div>
            <div x-show="tab==='rkas'" x-transition>
                <div class="border-b border-slate-100 px-5 py-3 bg-slate-50/30">
                    <h3 class="font-bold text-slate-800">Rincian Anggaran RKAS (Tanpa Triwulan)</h3>
                    <p class="text-base text-slate-500">Filter Bulan/Triwulan/Semester/Tahun · 15,25,50,100,All dalam 1 page_table · tanpa kolom 1–4</p>
                </div>
                <div class="p-0">
                        <livewire:rkas-table lazy />
                </div>
            </div>
        </section>
        @endif

        <section class="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-800">Kegiatan dengan Pagu Terbesar</h2><p class="mt-1 text-base text-slate-500">Enam prioritas berdasarkan anggaran RKAS.</p></div><a href="{{ route('rkas-budget.index') }}" class="text-base font-bold theme-text">Lihat RKAS →</a></div><div class="overflow-x-auto"><table class="min-w-full text-base"><thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Kegiatan</th><th class="px-5 py-3 text-right">Anggaran</th><th class="px-5 py-3 text-right">Realisasi</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($activities as $a)<tr><td class="max-w-xs px-5 py-3"><p class="font-semibold text-slate-800">{{ $a->activity_code ?: '—' }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $a->activity_name ?: 'Kegiatan belum bernama' }}</p></td><td class="px-5 py-3 text-right font-medium text-slate-700">{{ $rupiah($a->budget) }}</td><td class="px-5 py-3 text-right"><p class="font-medium text-emerald-700">{{ $rupiah($a->realization) }}</p><p class="text-xs text-slate-400">{{ number_format($a->percentage,1,',','.') }}%</p></td></tr>@empty<tr><td colspan="3" class="px-5 py-8 text-center text-slate-500">Data RKAS belum tersedia.</td></tr>@endforelse</tbody></table></div></article>
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Transaksi Terbaru</h2><p class="mt-1 text-base text-slate-500">Enam transaksi BKU terbaru.</p></div><div class="divide-y divide-slate-100">@forelse($recentTransactions as $t)<div class="px-5 py-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-semibold text-slate-800">{{ $t->no_bukti }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $t->description ?: $t->recipient_name ?: 'Tanpa uraian' }}</p></div><p class="shrink-0 text-base font-bold text-slate-700">{{ $rupiah($t->gross_amount) }}</p></div><div class="mt-2 flex items-center justify-between text-xs text-slate-400"><span>{{ $t->transaction_date ? \Illuminate\Support\Carbon::parse($t->transaction_date)->translatedFormat('d F Y') : '—' }}</span><span>{{ $t->payment_method ?: 'Cara bayar belum diisi' }}</span></div></div>@empty<p class="px-5 py-8 text-center text-base text-slate-500">Belum ada transaksi tersinkron.</p>@endforelse</div></article>
        </section>
    </div>
    <script id="dashboard-chart-data" type="application/json">{!! $chartPayload !!}</script>
    <script id="hierarchy-chart-data" type="application/json">{!! $hierarchyPayload !!}</script>
    <script>
        (()=> {
            const s=document.getElementById('hierarchy-search');
            const levelSel=document.getElementById('hierarchy-level');
            const tbody=document.getElementById('hierarchy-tbody');
            const applyFilters = ()=>{
                const q=(s?.value||'').toLowerCase().trim();
                const lvl=levelSel?.value||'all';
                document.querySelectorAll('#hierarchy-tbody tr, #hierarchy-cards .h-card').forEach(el=>{
                    const hay=(el.dataset.code||'') + ' ' + (el.dataset.name||'');
                    const bySearch = !q || hay.includes(q);
                    const byLevel = lvl==='all' || el.dataset.level===lvl;
                    el.style.display = (bySearch && byLevel) ? '' : 'none';
                });
                // update chart: filter all data by level, ambil top 6
                const allEl=document.getElementById('hierarchy-all-data');
                const chartEl=document.getElementById('hierarchy-bar-chart');
                if(allEl && chartEl && window.Chart){
                    try{
                        const allData=JSON.parse(allEl.textContent);
                        const filtered = lvl==='all' ? allData : allData.filter(d=> String(d.level)===String(lvl));
                        const top=[...filtered].sort((a,b)=> b.budget - a.budget).slice(0,6);
                        const chart=window.Chart.getChart(chartEl);
                        if(chart){
                            chart.data.labels = top.map(d=> d.code);
                            chart.data.datasets[0].data = top.map(d=> d.budget);
                            chart.data.datasets[1].data = top.map(d=> d.realization);
                            chart.options.plugins.tooltip.callbacks.title = (items)=>{ const d=top[items[0].dataIndex]; return d ? d.code+' — '+d.name+' (L'+d.level+')' : ''; };
                            chart.update();
                        }
                    }catch(e){}
                }
            };
            if(s) s.addEventListener('input', applyFilters);
            if(levelSel) levelSel.addEventListener('change', applyFilters);
            const ths=document.querySelectorAll('#hierarchy-table th[data-sort]');
            if(tbody&&ths.length){
                let dir='desc', key='budget';
                ths.forEach(th=>{
                    th.addEventListener('click',()=>{
                        const k=th.dataset.sort;
                        if(key===k) dir=dir==='asc'?'desc':'asc'; else{key=k; dir=(k==='code'||k==='name'||k==='level')?'asc':'desc';}
                        ths.forEach(x=>{ const ic=x.querySelector('.sort-icon'); if(ic) ic.textContent='↕'; });
                        const ic=th.querySelector('.sort-icon'); if(ic) ic.textContent=dir==='asc'?'↑':'↓';
                        const rows=[...tbody.querySelectorAll('tr')].filter(r=>r.dataset.code);
                        rows.sort((a,b)=>{
                            let va=a.dataset[k], vb=b.dataset[k];
                            const num=['budget','real','sisa','serap','presentasi','level'].includes(k);
                            if(num){ va=parseFloat(va)||0; vb=parseFloat(vb)||0; return dir==='asc'?va-vb:vb-va; }
                            return dir==='asc'? String(va).localeCompare(String(vb)): String(vb).localeCompare(String(va));
                        });
                        rows.forEach(r=>tbody.appendChild(r));
                    });
                });
            }
            // init
            applyFilters();
        })();
    </script>
</x-layouts.tailwind-app>
