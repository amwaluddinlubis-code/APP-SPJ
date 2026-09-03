<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.'))
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow"><div class="theme-header px-5 py-6 text-white"><p class="text-xs font-bold tracking-[.16em] text-white/70">LAPORAN SPJ</p><h1 class="mt-2 text-2xl font-bold">Rekap Paket SPJ</h1><p class="mt-1 text-base text-white/80">Hanya memuat paket yang sudah memiliki nomor dokumen SPJ.</p></div><form class="flex flex-wrap items-end gap-3 px-5 py-4" method="GET"><div><label class="text-xs font-bold text-slate-500">BULAN</label><select name="month" class="mt-1 block rounded-lg border-slate-300 text-base"><option value="">Semua bulan</option>@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(request('month') == $month)>{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>@endforeach</select></div><div><label class="text-xs font-bold text-slate-500">TRIWULAN</label><select name="quarter" class="mt-1 block rounded-lg border-slate-300 text-base"><option value="">Semua triwulan</option>@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}" @selected(request('quarter') == $quarter)>Triwulan {{ $quarter }}</option>@endforeach</select></div><div><label class="text-xs font-bold text-slate-500">SEMESTER</label><select name="semester" class="mt-1 block rounded-lg border-slate-300 text-base"><option value="">Semua semester</option><option value="1" @selected(request('semester') == 1)>Semester 1</option><option value="2" @selected(request('semester') == 2)>Semester 2</option></select></div><button class="rounded-lg theme-btn px-4 py-2.5 text-base font-bold">Terapkan</button><a href="{{ route('spj-reports.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-base font-bold text-rose-700">Pratinjau PDF</a><a href="{{ route('spj-reports.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-base font-bold text-emerald-700">Unduh Excel</a></form></section>
        <section class="grid gap-4 sm:grid-cols-3">@foreach([['Paket bernomor',$summary['count'],'text-indigo-700'],['Nilai bruto',$rupiah($summary['gross']),'text-slate-800'],['Nilai dibayarkan',$rupiah($summary['net']),'text-emerald-700']] as [$label,$value,$color])<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow hover:shadow transition"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-2 text-xl font-bold {{ $color }}">{{ $value }}</p></div>@endforeach</section>
        <section class="rounded-2xl border border-slate-200 bg-white shadow"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Rekap Pajak Paket Bernomor</h2><p class="mt-1 text-base text-slate-500">Nilai ini mengikuti paket dalam filter bulan dan triwulan di atas.</p></div><div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0 lg:grid-cols-6">@foreach([['PPN','ppn','text-indigo-700'],['PPh 21','pph21','text-sky-700'],['PPh 22','pph22','text-violet-700'],['PPh 23','pph23','text-amber-700'],['PPh 4(2)','pph4','text-orange-700'],['SSPD','sspd','text-rose-700']] as [$label,$key,$color])<div class="px-4 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-1 font-bold {{ $color }}">{{ $rupiah($summary[$key]) }}</p></div>@endforeach</div></section>

        {{-- TAB: Daftar Paket & Monitoring Belum Lengkap — sesuai aturan 15,25,50,100 All dalam 1 page_table --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow" x-data="{tab: Alpine.$persist('paket')}" id="laporan-tabs">
            <div class="border-b border-slate-200 bg-slate-50/70">
                <nav class="flex gap-1 overflow-x-auto px-2 py-1 text-base">
                    <button @click="tab='paket'" :data-active="tab==='paket'" class="whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:theme-text data-[active=true]:shadow-sm data-[active=false]:bg-transparent data-[active=false]:border-transparent text-slate-600 hover:text-slate-800 transition">📦 Daftar Paket <span class="ml-1 rounded-full theme-bg-soft px-1.5 py-0.5 text-xs theme-text">{{ $packages->total() }}</span></button>
                    <button @click="tab='monitoring'" :data-active="tab==='monitoring'" class="whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:theme-text data-[active=true]:shadow-sm data-[active=false]:bg-transparent data-[active=false]:border-transparent text-slate-600 hover:text-slate-800 transition">⚠️ Monitoring Belum Lengkap <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">{{ $pendingPaginator->total() }}</span></button>
                </nav>
            </div>

            {{-- Tab: Daftar Paket --}}
            <div x-show="tab==='paket'" x-transition>
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div><h2 class="font-bold text-slate-800">Daftar Paket</h2><p class="mt-1 text-base text-slate-500">Hanya paket bernomor · <span class="font-mono font-bold text-slate-700">{{ $packages->total() }} paket</span> · klik header untuk sort · tema ikut pilihan Anda</p></div>
                    <x-page-table-per-page :total="$packages->total()" />
                </div>
                {{-- Mobile cards --}}
                <div class="grid gap-3 p-4 lg:hidden">
                    @forelse($packages as $package)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow hover:shadow transition">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="font-mono text-base font-bold theme-text">{{ $package->document_number }}</a>
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">{{ $rupiah($package->transaction->net_amount) }}</span>
                            </div>
                            <p class="mt-1 font-semibold text-slate-800">{{ $package->transaction->no_bukti }} · <span class="text-xs text-slate-500">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</span></p>
                            <p class="mt-1 text-base text-slate-600 truncate">{{ $package->transaction->recipient_name ?: '—' }}</p>
                            <div class="mt-2 flex gap-2 text-base"><span class="flex-1 rounded bg-slate-50 px-2 py-1 text-center">Bruto {{ $rupiah($package->transaction->gross_amount) }}</span><span class="flex-1 rounded bg-amber-50 px-2 py-1 text-center text-amber-700">Pajak {{ $rupiah($package->transaction->tax_total) }}</span></div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed p-8 text-center text-slate-500">Belum ada paket SPJ bernomor untuk filter ini.</div>
                    @endforelse
                </div>
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-base" id="laporan-table">
                        <thead class="bg-slate-50">
                            <tr>
                                <th data-sort="nomor" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition">NOMOR SPJ <span class="sort-icon opacity-40">↕</span></th>
                                <th data-sort="bukti" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition">BUKTI / TANGGAL <span class="sort-icon opacity-40">↕</span></th>
                                <th data-sort="penerima" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition">PENERIMA <span class="sort-icon opacity-40">↕</span></th>
                                <th data-sort="bruto" class="cursor-pointer select-none px-4 py-3 text-right text-xs font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition">BRUTO <span class="sort-icon opacity-40">↕</span></th>
                                <th data-sort="pajak" class="cursor-pointer select-none px-4 py-3 text-right text-xs font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition">PAJAK <span class="sort-icon opacity-40">↕</span></th>
                                <th data-sort="dibayar" class="cursor-pointer select-none px-4 py-3 text-right text-xs font-bold text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition">DIBAYARKAN <span class="sort-icon opacity-40">↕</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="laporan-tbody">
                            @forelse($packages as $package)
                                <tr data-nomor="{{ $package->document_number }}" data-bukti="{{ $package->transaction->no_bukti }}" data-penerima="{{ strtolower($package->transaction->recipient_name ?? '') }}" data-bruto="{{ (float)$package->transaction->gross_amount }}" data-pajak="{{ (float)$package->transaction->tax_total }}" data-dibayar="{{ (float)$package->transaction->net_amount }}" class="hover:bg-indigo-50/40 transition">
                                    <td class="px-4 py-3 font-mono text-xs font-bold theme-text"><a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="hover:underline">{{ $package->document_number }}</a></td>
                                    <td class="px-4 py-3"><p class="font-semibold">{{ $package->transaction->no_bukti }}</p><p class="text-xs text-slate-500">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td>
                                    <td class="px-4 py-3">{{ $package->transaction->recipient_name }}</td>
                                    <td class="px-4 py-3 text-right">{{ $rupiah($package->transaction->gross_amount) }}</td>
                                    <td class="px-4 py-3 text-right text-amber-700">{{ $rupiah($package->transaction->tax_total) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-700">{{ $rupiah($package->transaction->net_amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-14 text-center text-slate-500">Belum ada paket SPJ bernomor untuk filter ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-100 px-5 py-4 bg-slate-50/30">
                    <span class="text-base text-slate-500">Menampilkan {{ $packages->firstItem() ?? 0 }}–{{ $packages->lastItem() ?? 0 }} dari {{ $packages->total() }}</span>
                    <div class="w-full sm:w-auto">{{ $packages->appends(request()->query())->links() }}</div>
                </div>
            </div>

            {{-- Tab: Monitoring Belum Lengkap — sesuai aturan 15,25,50,100 All, sortable, responsive, theme, md --}}
            <div x-show="tab==='monitoring'" x-transition class="hidden" :class="tab==='monitoring' ? '!block' : ''">
                <div class="border-b border-amber-100 bg-amber-50/40 px-5 py-4 sm:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div><h2 class="font-bold text-amber-900">Monitoring Dokumen Belum Lengkap</h2><p class="mt-1 text-base text-amber-800">Transaksi ber-rincian tapi paket belum siap atau belum bernomor · <span class="font-bold">{{ $pendingPaginator->total() }} transaksi</span> · klik header untuk sort</p></div>
                    <div class="flex items-center gap-2">
                        <select onchange="const u=new URL(window.location); u.searchParams.set('pendingPerPage', this.value); u.searchParams.delete('pending_page'); window.location=u.toString()" class="rounded-lg border border-amber-200 bg-white px-2 py-1 text-base font-bold text-amber-800">
                            @foreach([15,25,50,100] as $opt)<option value="{{ $opt }}" @selected((string)request('pendingPerPage','15')===(string)$opt)>{{ $opt }}</option>@endforeach
                            <option value="all" @selected(request('pendingPerPage')==='all')>All</option>
                        </select>
                        <span class="text-xs text-amber-700 hidden sm:inline">/halaman</span>
                    </div>
                </div>
                {{-- Mobile cards --}}
                <div class="grid gap-3 p-4 lg:hidden">
                    @forelse($pendingPaginator as $transaction)
                        <article class="rounded-xl border border-amber-200 bg-white p-4 shadow hover:shadow transition">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-mono text-base font-bold text-amber-900">{{ $transaction->no_bukti }}</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $transaction->spjPackage ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-600' }}">{{ $transaction->spjPackage ? 'Draft' : 'Belum siap' }}</span>
                            </div>
                            <p class="mt-1 text-base text-slate-700 line-clamp-2">{{ $transaction->description ?: '—' }}</p>
                            <p class="mt-1 text-base text-slate-500">{{ $transaction->spjPackage ? 'Nomor belum ditetapkan' : 'Paket belum disiapkan' }}</p>
                            <div class="mt-3 text-right">
                                @if($transaction->spjPackage)<a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}" class="inline-flex rounded-lg theme-btn px-3 py-1.5 text-base font-bold">Lengkapi →</a>
                                @else<a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="inline-flex rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-base font-bold hover:bg-slate-50">Buka persiapan →</a>@endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-8 text-center text-emerald-700">Tidak ada transaksi ber-rincian yang tertunda.</div>
                    @endforelse
                </div>
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-amber-100 text-base" id="monitoring-table">
                        <thead class="bg-amber-50">
                            <tr>
                                <th data-sort="bukti" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-amber-800 hover:bg-amber-100 transition">BUKTI <span class="sort-icon opacity-40">↕</span></th>
                                <th data-sort="uraian" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-amber-800 hover:bg-amber-100 transition">URAIAN <span class="sort-icon opacity-40">↕</span></th>
                                <th data-sort="status" class="cursor-pointer select-none px-4 py-3 text-left text-xs font-bold text-amber-800 hover:bg-amber-100 transition">STATUS <span class="sort-icon opacity-40">↕</span></th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-amber-800">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100" id="monitoring-tbody">
                            @forelse($pendingPaginator as $transaction)
                                <tr data-bukti="{{ $transaction->no_bukti }}" data-uraian="{{ strtolower($transaction->description ?? '') }}" data-status="{{ $transaction->spjPackage ? 'draft' : 'belum' }}" class="hover:bg-amber-50/60 transition">
                                    <td class="px-4 py-3 font-mono font-bold text-amber-900">{{ $transaction->no_bukti }}</td>
                                    <td class="px-4 py-3 max-w-sm truncate">{{ $transaction->description }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $transaction->spjPackage ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-500' }}">{{ $transaction->spjPackage ? 'Draft — nomor belum ditetapkan' : 'Paket belum disiapkan' }}</span></td>
                                    <td class="px-4 py-3 text-right">@if($transaction->spjPackage)<a href="{{ route('spj-documents.show', $transaction->spjPackage->id) }}" class="font-bold theme-text hover:underline">Lengkapi paket →</a>@else<a href="{{ route('spj-documents.index', ['state' => 'unprepared']) }}" class="font-bold theme-text hover:underline">Buka persiapan →</a>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center text-emerald-700">Tidak ada transaksi ber-rincian yang tertunda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-amber-100 px-5 py-4 bg-amber-50/20">
                    <span class="text-base text-slate-500">Menampilkan {{ $pendingPaginator->firstItem() ?? 0 }}–{{ $pendingPaginator->lastItem() ?? 0 }} dari {{ $pendingPaginator->total() }}</span>
                    <div class="w-full sm:w-auto">{{ $pendingPaginator->appends(request()->query())->links() }}</div>
                </div>
            </div>
        </section>
        <script>
            (()=>{
                const ths=document.querySelectorAll('#laporan-table th[data-sort]'); const tbody=document.getElementById('laporan-tbody');
                if(tbody&&ths.length){ let dir='asc',key=null; ths.forEach(th=>{ th.addEventListener('click',()=>{ const k=th.dataset.sort; if(key===k) dir=dir==='asc'?'desc':'asc'; else{key=k; dir='asc';} ths.forEach(x=>x.querySelector('.sort-icon').textContent='↕'); th.querySelector('.sort-icon').textContent=dir==='asc'?'↑':'↓'; const rows=[...tbody.querySelectorAll('tr')].filter(r=>r.dataset.nomor); rows.sort((a,b)=>{ let va=a.dataset[k], vb=b.dataset[k]; const num=['bruto','pajak','dibayar'].includes(k); if(num){ va=parseFloat(va)||0; vb=parseFloat(vb)||0; return dir==='asc'?va-vb:vb-va;} return dir==='asc'? String(va).localeCompare(String(vb)): String(vb).localeCompare(String(va)); }); rows.forEach(r=>tbody.appendChild(r)); }); }); }
                const mths=document.querySelectorAll('#monitoring-table th[data-sort]'); const mtbody=document.getElementById('monitoring-tbody');
                if(mtbody&&mths.length){ let mdir='asc', mkey=null; mths.forEach(th=>{ th.addEventListener('click',()=>{ const k=th.dataset.sort; if(mkey===k) mdir=mdir==='asc'?'desc':'asc'; else{mkey=k; mdir='asc';} mths.forEach(x=>x.querySelector('.sort-icon').textContent='↕'); th.querySelector('.sort-icon').textContent=mdir==='asc'?'↑':'↓'; const rows=[...mtbody.querySelectorAll('tr')].filter(r=>r.dataset.bukti); rows.sort((a,b)=>{ let va=a.dataset[k]||'', vb=b.dataset[k]||''; return mdir==='asc'? String(va).localeCompare(String(vb)): String(vb).localeCompare(String(va)); }); rows.forEach(r=>mtbody.appendChild(r)); }); }); }
            })();
        </script>
        <section class="grid gap-6 xl:grid-cols-2"><article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Realisasi per Kegiatan</h2><p class="mt-1 text-base text-slate-500">Seluruh transaksi BKU pada tahun aktif.</p></div><div class="max-h-[32rem] overflow-auto"><table class="min-w-full text-base"><thead class="sticky top-0 bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">KODE</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">NAMA KEGIATAN</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">REALISASI</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($summary['activities'] as $row)<tr><td class="px-4 py-3 font-mono text-xs text-indigo-700">{{ $row->activity_code }}</td><td class="px-4 py-3">{{ $row->activity_name }}</td><td class="px-4 py-3 text-right font-bold text-emerald-700">{{ $rupiah($row->realization) }}</td></tr>@empty<tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada realisasi.</td></tr>@endforelse</tbody></table></div></article><article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Realisasi per Rekening</h2><p class="mt-1 text-base text-slate-500">Dikelompokkan berdasarkan kode rekening transaksi.</p></div><div class="max-h-[32rem] overflow-auto"><table class="min-w-full text-base"><thead class="sticky top-0 bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">KODE</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">NAMA REKENING</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">REALISASI</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($summary['accounts'] as $row)<tr><td class="px-4 py-3 font-mono text-xs text-indigo-700">{{ $row->account_code }}</td><td class="px-4 py-3">{{ $row->account_name }}</td><td class="px-4 py-3 text-right font-bold text-emerald-700">{{ $rupiah($row->realization) }}</td></tr>@empty<tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada realisasi.</td></tr>@endforelse</tbody></table></div></article></section>
    </div>
</x-layouts.tailwind-app>
