<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
    @php($spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
        'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
        default => str_replace('_', ' ', (string) $value),
    })
    @php($spjProgress = ($totalPackages ?? 0) > 0 ? min(100, (int) round((($numberedPackages ?? 0) / $totalPackages) * 100)) : 0)
    <div class="space-y-6" x-data="{
        tab: '{{ $tab ?? 'persiapan' }}',
        loadingTab: false,
        changeTab(name) {
            if (this.tab === name || this.loadingTab) {
                return;
            }
            this.loadingTab = true;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', name);
            if (name !== 'paket') url.searchParams.delete('package_id');
            window.location.assign(url.toString());
        }
    }" @click="const button = $event.target.closest('[data-tab]'); if (button) { $event.preventDefault(); changeTab(button.dataset.tab); }">
        {{-- Unified Header --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <div aria-hidden="true" class="absolute -right-20 -top-28 h-72 w-72 rounded-full bg-sky-400/20 blur-3xl"></div>
                <div aria-hidden="true" class="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-violet-400/20 blur-3xl"></div>
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="mb-4"><x-breadcrumb :items="[['label' => 'Pusat Dokumen SPJ']]" :on-dark="true" /></div>
                        <p class="text-[11px] font-bold uppercase tracking-[.2em] text-sky-200">Manajemen dokumen pertanggungjawaban</p>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Pusat Dokumen SPJ</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100 sm:text-base">Kelola alur dari transaksi siap, kelengkapan paket, hingga dokumen bernomor dalam satu ruang kerja.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('transactions.index') }}" class="inline-flex items-center rounded-lg border border-white/20 bg-white/10 px-3.5 py-2 text-sm font-bold text-white backdrop-blur hover:bg-white/20">Lihat transaksi</a>
                        <button type="button" data-tab="monitoring" class="inline-flex items-center rounded-lg bg-white px-3.5 py-2 text-sm font-bold text-indigo-900 shadow hover:bg-indigo-50">Periksa kendala</button>
                    </div>
                </div>
            </div>
            <div class="grid gap-px bg-slate-200 sm:grid-cols-3">
                <button type="button" data-tab="persiapan" class="group bg-white px-5 py-4 text-left transition hover:bg-slate-50 sm:px-6">
                    <div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Transaksi siap</p><span class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500">→</span></div>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($readyTransactions ?? 0, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Siap diproses menjadi paket</p>
                </button>
                <button type="button" data-tab="paket" class="group bg-white px-5 py-4 text-left transition hover:bg-slate-50 sm:px-6">
                    <div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Paket dibuat</p><span class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500">→</span></div>
                    <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($totalPackages ?? 0, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Draft dan paket siap nomor</p>
                </button>
                <div class="bg-white px-5 py-4 sm:px-6">
                    <div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sudah bernomor</p><span class="text-xs font-bold text-emerald-700">{{ $spjProgress }}%</span></div>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($numberedPackages ?? 0, 0, ',', '.') }}</p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $spjProgress }}%"></div></div>
                </div>
            </div>
        </section>

        {{-- Tab Navigation --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div id="spj-main-tabs">
                <x-tabs
                    :tabs="[
                        ['id' => 'persiapan', 'label' => '📦 Persiapan'],
                        ['id' => 'paket', 'label' => '📄 Paket'],
                        ['id' => 'laporan', 'label' => '📊 Laporan'],
                        ['id' => 'monitoring', 'label' => '⚠️ Monitoring']
                    ]"
                    :activeTab="$tab"
                />
            </div>

            {{-- Tab: Persiapan --}}
            <div x-show="tab === 'persiapan'" x-transition>
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="font-bold text-slate-900">Antrean persiapan SPJ</h2><p class="mt-1 text-sm text-slate-500">Pilih transaksi, periksa rincian, lalu siapkan paket dokumennya.</p></div><p class="max-w-xl text-xs font-medium text-slate-400">Prioritas: draft perlu dilengkapi → belum disiapkan → sudah bernomor. Dalam setiap kelompok, tanggal terlama tampil lebih dahulu.</p></div>
                    <form method="GET" class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                        <input type="hidden" name="tab" value="persiapan">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="text-xs font-bold text-slate-600"><span class="mb-1.5 block uppercase tracking-wide">Bulan</span><select name="month" class="w-full rounded-lg border-slate-300 bg-white text-sm"><option value="">Semua bulan</option>@foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $index => $month)<option value="{{ $index + 1 }}" @selected(($filters['month'] ?? null) == $index + 1)>{{ $month }}</option>@endforeach</select></label>
                            <label class="text-xs font-bold text-slate-600"><span class="mb-1.5 block uppercase tracking-wide">Triwulan</span><select name="quarter" class="w-full rounded-lg border-slate-300 bg-white text-sm"><option value="">Semua triwulan</option>@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}" @selected(($filters['quarter'] ?? null) == $quarter)>Triwulan {{ $quarter }}</option>@endforeach</select></label>
                            <label class="text-xs font-bold text-slate-600"><span class="mb-1.5 block uppercase tracking-wide">Kategori</span><select name="spj_category" class="w-full rounded-lg border-slate-300 bg-white text-sm"><option value="">Semua jenis SPJ</option>@foreach($spjTypes ?? [] as $type)<option value="{{ $type }}" @selected(($filters['spj_category'] ?? null) === $type)>{{ $spjTypeLabel($type) }}</option>@endforeach</select></label>
                            <label class="text-xs font-bold text-slate-600"><span class="mb-1.5 block uppercase tracking-wide">Status</span><select name="state" class="w-full rounded-lg border-slate-300 bg-white text-sm"><option value="all">Semua status</option><option value="ready" @selected(($filters['state'] ?? null) === 'ready')">Siap dibuat</option><option value="unprepared" @selected(($filters['state'] ?? null) === 'unprepared')">Belum disiapkan</option><option value="draft" @selected(($filters['state'] ?? null) === 'draft')">Draft paket</option><option value="numbered" @selected(($filters['state'] ?? null) === 'numbered')">Sudah bernomor</option></select></label>
                        </div>
                        <div class="mt-3 flex flex-wrap justify-end gap-2"><a href="{{ route('spj.index', ['tab' => 'persiapan']) }}" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-600 hover:bg-white hover:text-slate-900">Reset filter</a><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">Terapkan filter</button></div>
                    </form>
                </div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-base"><thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Bukti / Tanggal</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Uraian / Penerima</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Kategori / Rincian</th><th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Nilai</th><th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Tindakan</th></tr></thead><tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($transactions ?? [] as $transaction)
                        <tr class="transition hover:bg-violet-50/50"><td class="px-5 py-4"><p class="font-mono font-bold text-violet-700">{{ $transaction->no_bukti }}</p><p class="mt-1 text-xs text-slate-500">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</p></td><td class="px-4 py-4">@if($transaction->spjPackage)<span class="rounded-full {{ $transaction->spjPackage->document_number ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-bold">{{ $transaction->spjPackage->document_number ? 'Bernomor' : 'Draft paket' }}</span><p class="mt-1 font-mono text-xs text-slate-500">{{ $transaction->spjPackage->document_number ?: 'Perlu dilengkapi' }}</p>@else<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">Belum disiapkan</span>@endif</td><td class="max-w-sm px-4 py-4"><p class="truncate font-semibold text-slate-800">{{ $transaction->payment_description ?: $transaction->description ?: 'Tanpa uraian' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $transaction->recipient_name ?: 'Penerima belum diisi' }}</p></td><td class="px-4 py-4"><p class="text-xs font-bold text-indigo-700">{{ $spjTypeLabel($transaction->spj_category) }}</p><p class="mt-1 text-xs text-slate-500">{{ $transaction->items_count }} rincian</p></td><td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-slate-800">{{ $rupiah($transaction->gross_amount) }}</td><td class="px-5 py-4 text-right">@if($transaction->spjPackage)<a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}" class="inline-flex rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100">Buka paket →</a>@elseif($transaction->items_count)<a href="{{ route('transactions.show', $transaction->id).'#modul-buat-spj' }}" class="inline-flex rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white shadow hover:bg-violet-700">Lengkapi &amp; siapkan →</a>@else<span class="text-xs text-slate-400">Rincian belum ada</span>@endif</td></tr>
                    @empty<tr><td colspan="6" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">Belum ada transaksi tersinkron.</p><p class="mt-1 text-base text-slate-500">Jalankan Sinkron Semua ARKAS terlebih dahulu.</p></td></tr>@endforelse
                </tbody></table></div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-100 px-5 py-4 bg-slate-50/30">
                    <x-page-table-per-page :total="$transactions?->total() ?? 0" />
                    <div class="w-full sm:w-auto">{{ $transactions?->links() ?? '' }}</div>
                </div>
            </div>

            {{-- Tab: Paket --}}
            <div x-show="tab === 'paket'" x-transition>
                @if(isset($package))
                    @php($transaction = $package->transaction)
                    @php($participantRows = $transaction->participants->map(fn ($participant) => ['name' => $participant->name, 'position' => $participant->position, 'portions' => (int) $participant->portions])->values()->all())
                    @if(strtoupper((string) $transaction->spj_category) === 'KONSUMSI' && $participantRows === [])
                        @php($participantRows = collect($participantRoster ?? [])->map(fn ($employee) => ['name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type, 'portions' => 1])->values()->all())
                    @endif
                    @php($participantRows = old('participants', $participantRows))
                    @php($activeSpjDocument = $package->documents->first(fn ($document) => $document->document_type === 'SPJ' && $document->scope_key === 'MAIN' && in_array($document->status, ['NUMBERED', 'FINAL'], true) && filled($document->document_number)))
                    @php($cancelledSpjDocument = $package->documents->where('document_type', 'SPJ')->where('scope_key', 'MAIN')->where('status', 'CANCELLED')->sortByDesc('id')->first())
                    {{-- Dokumen adalah sumber kebenaran penomoran. Status DICETAK tetap memiliki nomor aktif. --}}
                    @php($hasActiveSpjNumber = $activeSpjDocument !== null && $package->status !== 'CANCELLED')
                    @php($packageCategory = strtoupper((string) $transaction->spj_category))
                    @php($isHonorPackage = in_array($packageCategory, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true))
                    @php($isGoodsPackage = in_array($packageCategory, ['BARANG', 'KONSUMSI'], true))
                    @php($isConsumptionPackage = $packageCategory === 'KONSUMSI')
                    @php($purchaseDetails = $transaction->goods->first())
                    @php($transactionDateLimit = $transaction->transaction_date?->format('Y-m-d'))
                    @php($orderDate = $purchaseDetails?->order_date?->format('Y-m-d') ?: $transaction->order_date?->format('Y-m-d') ?: $transactionDateLimit)
                    @php($bapDate = $purchaseDetails?->bap_date?->format('Y-m-d') ?: $transaction->bap_date?->format('Y-m-d') ?: $transactionDateLimit)
                    @php($bastDate = $purchaseDetails?->bast_date?->format('Y-m-d') ?: $transaction->bast_date?->format('Y-m-d') ?: $transactionDateLimit)
                    <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
                        <a href="{{ route('spj.index', ['tab' => 'persiapan']) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-base font-semibold text-slate-700 shadow hover:bg-slate-50">← Semua paket</a>
                        <a href="{{ route('transactions.show', $transaction->id) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-base font-semibold text-indigo-700 hover:bg-indigo-100">Lihat transaksi</a>
                    </div>

                    <section class="mx-5 mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
                        <div class="bg-gradient-to-r from-violet-950 via-indigo-900 to-sky-800 px-4 py-5 text-white sm:px-5">
                            <p class="text-[11px] font-bold tracking-[.16em] text-sky-200">PAKET DOKUMEN SPJ</p>
                            <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h1 class="font-mono text-2xl font-bold sm:text-3xl">{{ $transaction->no_bukti }}</h1>
                                    <p class="mt-1.5 text-base text-indigo-100 line-clamp-2">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian transaksi belum tersedia.' }}</p>
                                </div>
                                <div class="rounded-lg bg-white/10 px-3 py-2.5 text-left lg:text-right ring-1 ring-white/20">
                                    <p class="text-[11px] font-semibold {{ $package->status === 'CANCELLED' ? 'text-rose-200' : 'text-indigo-200' }}">{{ $package->status === 'CANCELLED' ? 'Nomor SPJ dibatalkan' : 'Nomor Dokumen SPJ' }}</p>
                                    <p class="mt-0.5 font-mono text-base font-bold {{ $package->status === 'CANCELLED' ? 'text-rose-100 line-through' : '' }}">{{ $hasActiveSpjNumber ? $activeSpjDocument->document_number : ($package->status === 'CANCELLED' ? ($cancelledSpjDocument?->document_number ?: $package->document_number) : 'Belum ditetapkan') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid divide-y divide-slate-100 md:grid-cols-3 md:divide-x md:divide-y-0">
                            <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Periode</p><p class="mt-1 text-base font-semibold text-slate-800">{{ $package->quarter_code }} · {{ $package->semester_code }}</p></div>
                            <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Penerima</p><p class="mt-1 text-base font-semibold text-slate-800">{{ $transaction->recipient_name ?: 'Belum diisi' }}</p></div>
                            <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Nilai Dibayarkan</p><p class="mt-1 text-base font-semibold text-emerald-700">{{ $rupiah($transaction->net_amount) }}</p></div>
                        </div>
                    </section>

                    <section class="mx-5 mt-5 overflow-hidden rounded-xl border {{ $validationIssues ? 'border-amber-200' : 'border-emerald-200' }} bg-white shadow">
                        <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div><h2 class="text-base font-bold text-slate-800">Validasi Sebelum Cetak</h2><p class="mt-0.5 text-base {{ $validationIssues ? 'text-amber-700' : 'text-emerald-700' }}">{{ $validationIssues ? count($validationIssues).' data wajib perlu dilengkapi sebelum PDF dibuat.' : 'Semua data wajib lengkap. Paket siap diunduh sebagai PDF.' }}</p></div>
                            @if($validationIssues)
                                <span class="w-fit rounded-md bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-700">Belum siap cetak</span>
                            @elseif($package->status === 'CANCELLED')
                                <span class="w-fit rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-bold text-rose-700">Penomoran dibatalkan</span>
                            @elseif($package->status === 'DRAFT')
                                <form method="POST" action="{{ route('spj.ready', $package->id) }}">@csrf<button class="rounded-md bg-indigo-600 px-3.5 py-1.5 text-base font-bold text-white shadow hover:bg-indigo-700">Tandai siap dinomori</button></form>
                            @else
                                <span class="w-fit rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-700">Siap dicetak</span>
                            @endif
                        </div>
                        @if($validationIssues)<div class="divide-y divide-amber-100 border-t border-amber-100 bg-amber-50/40">@foreach($validationIssues as $issue)<div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-base"><div><span class="font-bold text-amber-800">{{ $issue['label'] }}</span><span class="ml-1.5 text-amber-700">{{ $issue['message'] }}</span></div><a href="{{ $issue['url'] }}" class="text-xs font-bold text-indigo-700 hover:text-indigo-900">Buka transaksi →</a></div>@endforeach</div>@endif
                    </section>

                    <section class="mx-5 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5">
                            <div><h2 class="text-base font-bold text-slate-800">Dokumen &amp; Template</h2><p class="mt-0.5 text-xs text-slate-500">PDF paket dibuat oleh aplikasi. Template Word/Excel diunduh dalam format sumbernya.</p></div>
                            @unless($validationIssues || $package->status === 'CANCELLED')
                                <form method="POST" action="{{ route('spj.download', $package->id) }}" target="_blank">@csrf<button class="rounded-md bg-rose-600 px-3.5 py-2 text-sm font-bold text-white shadow hover:bg-rose-700">Unduh Paket PDF</button></form>
                            @endunless
                        </div>
                        @if($templates->isNotEmpty())
                            <div class="divide-y divide-slate-100">
                                @foreach($templates as $template)
                                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 odd:bg-white even:bg-slate-50/70">
                                        <div><p class="font-semibold text-slate-800">{{ $template->name }}</p><p class="mt-0.5 font-mono text-[11px] text-violet-700">{{ $template->document_type }} · {{ strtoupper($template->format) }}</p></div>
                                        <div class="flex items-center gap-2">
                                            @if(strtolower($template->format) === 'xlsx')<a href="{{ route('spj.preview-template', [$package->id, $template->id]) }}" target="_blank" class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100">Pratinjau</a>@endif
                                            @if($validationIssues)<span class="rounded-md bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700">Lengkapi validasi dahulu</span>@elseif($package->status === 'CANCELLED')<span class="rounded-md bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Nomor dibatalkan</span>@else<form method="POST" action="{{ route('spj.download-template', [$package->id, $template->id]) }}">@csrf<button class="rounded-md bg-violet-600 px-3 py-2 text-xs font-bold text-white hover:bg-violet-700">Unduh {{ strtoupper($template->format) }}</button></form>@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="px-4 py-6 text-center text-sm text-slate-500">Belum ada template aktif yang sesuai dengan kategori {{ $spjTypeLabel($packageCategory) }}.</div>
                        @endif
                    </section>

                    <section class="mx-5 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow" x-data="{ packageTab: 'rincian' }">
                        <div class="border-b border-slate-200 bg-slate-50/70">
                            <nav class="flex gap-1 overflow-x-auto px-2 py-1 text-base" @click="const button = $event.target.closest('[data-package-tab]'); if (button) packageTab = button.dataset.packageTab">
                                <button type="button" data-package-tab="rincian" :data-active="packageTab === 'rincian'" class="whitespace-nowrap rounded-md border border-transparent px-3 py-2 text-base font-bold text-slate-600 hover:text-slate-800 data-[active=true]:border-slate-200 data-[active=true]:bg-white data-[active=true]:text-indigo-700 data-[active=true]:shadow">📦 Rincian <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px]">{{ $transaction->items->count() }}</span></button>
                                <button type="button" data-package-tab="isian" :data-active="packageTab === 'isian'" class="whitespace-nowrap rounded-md border border-transparent px-3 py-2 text-base font-bold text-slate-600 hover:text-slate-800 data-[active=true]:border-slate-200 data-[active=true]:bg-white data-[active=true]:text-indigo-700 data-[active=true]:shadow">✏️ Isian Manual</button>
                                <button type="button" data-package-tab="penomoran" :data-active="packageTab === 'penomoran'" class="whitespace-nowrap rounded-md border border-transparent px-3 py-2 text-base font-bold text-slate-600 hover:text-slate-800 data-[active=true]:border-slate-200 data-[active=true]:bg-white data-[active=true]:text-indigo-700 data-[active=true]:shadow">🔢 Penomoran @if($hasActiveSpjNumber)<span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] text-emerald-700">OK</span>@elseif($package->status === 'CANCELLED')<span class="ml-1 rounded-full bg-rose-100 px-1.5 py-0.5 text-[11px] text-rose-700">Dibatalkan</span>@else<span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-700">Belum</span>@endif</button>
                            </nav>
                        </div>

                        <div x-show="packageTab === 'rincian'" data-panel="rincian" class="tab-panel">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                <div><h2 class="text-base font-bold text-slate-800">Rincian Paket</h2><p class="mt-0.5 text-xs text-slate-500">{{ $transaction->items->count() }} item yang akan dipakai dokumen SPJ.</p></div>
                                <span class="hidden sm:inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $rupiah($transaction->items->sum('amount')) }}</span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @forelse($transaction->items as $index => $item)
                                    <div class="flex gap-3 px-4 py-3">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-500">{{ $index + 1 }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-base font-medium leading-tight text-slate-800">{{ $item->item_description ?: $item->description }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $item->quantity }} {{ $item->unit }} · {{ $item->account_code ?: $transaction->account_code }}</p>
                                        </div>
                                        <p class="shrink-0 text-base font-semibold text-slate-800">{{ $rupiah($item->amount) }}</p>
                                    </div>
                                @empty
                                    <p class="px-4 py-8 text-center text-base text-slate-500">Tidak ada rincian barang/jasa.</p>
                                @endforelse
                            </div>
                        </div>

                        <div x-show="packageTab === 'isian'" data-panel="isian" class="tab-panel" x-data="{saving:false}">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <h2 class="text-base font-bold text-slate-800">Isian Manual Paket SPJ</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Hanya isian kuning yang wajib. Bagian biru tampil sesuai kategori.</p>
                            </div>
                            @php($workerRows = $transaction->workers->concat(collect(array_fill(0, max(2, 6 - $transaction->workers->count()), null))))
                            @php($selectedSpjType = strtoupper((string) old('spj_category', $transaction->spj_category ?: $transaction->spj_category)))
                            <form id="spj-manual-form" method="POST" action="{{ route('spj.update', $package->id) }}" class="space-y-4 p-4" @submit="saving=true">@csrf @method('PUT')
                    @unless($package->isEditable())<div class="flex items-start gap-2 rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-700"><span aria-hidden="true">🔒</span><p><strong>Isian terkunci.</strong> Batalkan nomor dan buka paket untuk koreksi agar field dapat diedit kembali.</p></div>@endunless
                    <fieldset @disabled(!$package->isEditable()) class="disabled:cursor-not-allowed disabled:opacity-60">
                    <div x-show="saving" class="flex items-center justify-center py-4"><x-loading-spinner /></div>
                    <div x-show="!saving">
                                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                                    <div class="grid gap-3">
                                        <div>
                                            <label class="text-xs font-bold text-amber-900">Kategori SPJ <span class="text-rose-600">*</span></label>
                                            <select id="spj-type" name="spj_category" class="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-base focus:border-amber-400 focus:ring-1 focus:ring-amber-200">
                                                <option value="">Pilih kategori</option>
                                                @foreach(['BARANG','KONSUMSI','PEMELIHARAAN','JASA_LAINNYA','SPPD','HONOR_PEGAWAI'] as $value)
                                                    <option value="{{ $value }}" @selected(in_array($selectedSpjType, ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) && in_array(strtoupper((string) $value), ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) || old('spj_category', $transaction->spj_category ?: $transaction->spj_category) === $value)>{{ $spjTypeLabel($value) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <p class="text-xs text-amber-800">Kategori menentukan field manual, dokumen pendukung, dan nomor yang diterbitkan. Subkategori terpisah tidak diperlukan.</p>
                                    </div>
                                </div>
                                <div x-data="{ gross: {{ (float) $transaction->gross_amount }}, ppn: {{ (float) ($transaction->ppn_rate ?? ((float) $transaction->gross_amount > 0 ? (float) $transaction->ppn / (float) $transaction->gross_amount * 100 : 0)) }}, pph21: {{ (float) ($transaction->pph21_rate ?? ((float) $transaction->gross_amount > 0 ? (float) $transaction->pph21 / (float) $transaction->gross_amount * 100 : 0)) }}, pph22: {{ (float) ($transaction->pph22_rate ?? ((float) $transaction->gross_amount > 0 ? (float) $transaction->pph22 / (float) $transaction->gross_amount * 100 : 0)) }}, pph23: {{ (float) ($transaction->pph23_rate ?? ((float) $transaction->gross_amount > 0 ? (float) $transaction->pph23 / (float) $transaction->gross_amount * 100 : 0)) }}, pph4: {{ (float) ($transaction->pph4_rate ?? ((float) $transaction->gross_amount > 0 ? (float) $transaction->pph4 / (float) $transaction->gross_amount * 100 : 0)) }}, sspd: {{ (float) ($transaction->sspd_rate ?? ((float) $transaction->gross_amount > 0 && (float) $transaction->sspd > 0 ? (float) $transaction->sspd / (float) $transaction->gross_amount * 100 : ($isConsumptionPackage ? 10 : 0))) }}, money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value); } }" class="space-y-3">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                        <h3 class="text-xs font-bold text-slate-800">{{ $isHonorPackage ? 'Identitas Pembayaran Honor' : 'Identitas Rekanan dan Pembayaran' }}</h3>
                                        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                            <div><label class="text-xs font-medium text-slate-700">{{ $isHonorPackage ? 'Nama Penerima Honor' : 'Nama Toko / Katering' }}</label><input name="vendor_name" value="{{ $transaction->vendor_name ?: $transaction->recipient_name }}" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-base"></div>
                                            @unless($isHonorPackage)<div><label class="text-xs font-medium text-slate-700">Nama Pemilik / Direktur</label><input name="vendor_owner" value="{{ $transaction->vendor_owner }}" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-base"></div><div><label class="text-xs font-medium text-slate-700">NPWP Rekanan</label><input name="vendor_npwp" value="{{ $transaction->vendor_npwp }}" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-base"></div>@endunless
                                            <div><label class="text-xs font-medium text-slate-700">Metode Pembayaran</label><select name="payment_method" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-base"><option value="transfer_bank" @selected($transaction->payment_method === 'transfer_bank')>Transfer Bank (CMS / Non Tunai)</option><option value="siplah" @selected($transaction->payment_method === 'siplah')>SiPLah Kemdikbud</option><option value="tunai" @selected($transaction->payment_method === 'tunai' || blank($transaction->payment_method))>Tunai Kas BOS</option></select></div>
                                            <div><label class="text-xs font-medium text-slate-700">Penerima Kuitansi</label><input name="receipt_recipient_name" value="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-base" placeholder="Boleh beda dari BKU"></div>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-slate-900 p-3 text-white">
                                        <h3 class="text-xs font-bold">Perhitungan Pajak & Total Pembayaran SPJ</h3>
                                        @if($isConsumptionPackage)<p class="mt-1 text-xs text-amber-200">Belanja konsumsi dapat dikenai SSPD/Pajak Daerah atau PPh 4(2). Periksa ketentuan daerah dan nilai pajak pada BKU sebelum menyimpan; sistem tidak menetapkan tarif secara otomatis.</p>@endif
                                        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                            @unless($isHonorPackage)<label class="text-xs text-slate-300">PPN (%)<input type="number" min="0" max="100" step=".01" name="ppn_rate" x-model.number="ppn" class="mt-1 w-full rounded-md border-slate-600 bg-slate-800 px-2.5 py-1.5 text-white"></label>@endunless
                                            <label class="text-xs text-slate-300">PPh 21 (%)<input type="number" min="0" max="100" step=".01" name="pph21_rate" x-model.number="pph21" class="mt-1 w-full rounded-md border-slate-600 bg-slate-800 px-2.5 py-1.5 text-white"></label>
                                            @unless($isHonorPackage)<label class="text-xs text-slate-300">PPh 22 (%)<input type="number" min="0" max="100" step=".01" name="pph22_rate" x-model.number="pph22" class="mt-1 w-full rounded-md border-slate-600 bg-slate-800 px-2.5 py-1.5 text-white"></label><label class="text-xs text-slate-300">PPh 23 (%)<input type="number" min="0" max="100" step=".01" name="pph23_rate" x-model.number="pph23" class="mt-1 w-full rounded-md border-slate-600 bg-slate-800 px-2.5 py-1.5 text-white"></label>@endunless
                                            @unless($isHonorPackage)<label class="text-xs text-slate-300">PPh 4(2) (%)<input type="number" min="0" max="100" step=".01" name="pph4_rate" x-model.number="pph4" class="mt-1 w-full rounded-md border-slate-600 bg-slate-800 px-2.5 py-1.5 text-white"></label><label class="text-xs text-slate-300">SSPD / Pajak Daerah (%)<input type="number" min="0" max="100" step=".01" name="sspd_rate" x-model.number="sspd" class="mt-1 w-full rounded-md border-slate-600 bg-slate-800 px-2.5 py-1.5 text-white"></label>@endunless
                                        </div>
                                        <div class="mt-3 rounded-md bg-slate-800 p-3 text-sm"><div class="flex justify-between"><span>Subtotal Bruto</span><span x-text="money(gross)"></span></div><div class="mt-1 flex justify-between text-amber-300"><span>Total Potongan Pajak</span><span x-text="money(gross * (ppn + pph21 + pph22 + pph23 + pph4 + sspd) / 100)"></span></div><div class="mt-2 flex justify-between text-orange-300"><span>PPh 4(2) / SSPD</span><span x-text="money(gross * (pph4 + sspd) / 100)"></span></div><div class="mt-2 border-t border-slate-600 pt-2 flex justify-between font-bold text-emerald-400"><span>TOTAL PEMBAYARAN SPJ</span><span x-text="money(gross * (1 - (ppn + pph21 + pph22 + pph23 + pph4 + sspd) / 100))"></span></div></div>
                                    </div>
                                </div>
                                @if($isHonorPackage)<div class="rounded-lg border border-sky-200 bg-sky-50 p-3"><div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-xs font-bold text-sky-900">Rincian Penerima Honor</h3><p class="mt-1 text-xs text-sky-700">{{ $transaction->honors->count() }} penerima. Nama, jabatan, bulan/kali, tarif, dan PPh 21 dikelola pada transaksi agar satu BPU tetap menghasilkan satu kuitansi.</p></div><a href="{{ route('transactions.show', $transaction->id).'#modul-buat-spj' }}" class="rounded-md bg-sky-700 px-3 py-1.5 text-xs font-bold text-white">Ubah rincian honor →</a></div></div>@endif
                                <div data-spj-section="BARANG KONSUMSI" class="rounded-lg border border-sky-200 bg-sky-50/60 p-3">
                                    <h3 class="text-xs font-bold text-sky-900">Dokumen Pembelian dan Serah Terima Barang</h3>
                                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                        <div><label class="text-xs font-medium text-sky-900">No. Pesanan <span class="text-emerald-700">(otomatis)</span></label><input readonly name="order_number" value="{{ $purchaseDetails?->order_number ?: $transaction->order_number }}" class="mt-1 w-full cursor-not-allowed rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-base text-slate-600" placeholder="Terbit setelah penomoran"></div>
                                        <div><label class="text-xs font-medium text-sky-900">Tanggal Pesanan <span class="text-rose-600">*</span></label><input required type="date" name="order_date" value="{{ $orderDate }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                                        <div><label class="text-xs font-medium text-sky-900">No. BAP <span class="text-emerald-700">(otomatis)</span></label><input readonly name="bap_number" value="{{ $purchaseDetails?->bap_number ?: $transaction->bap_number }}" class="mt-1 w-full cursor-not-allowed rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-base text-slate-600" placeholder="Terbit setelah penomoran"></div>
                                        <div><label class="text-xs font-medium text-sky-900">Tanggal BAP <span class="text-rose-600">*</span></label><input required type="date" name="bap_date" value="{{ $bapDate }}" min="{{ $orderDate }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                                        <div><label class="text-xs font-medium text-sky-900">No. BAST <span class="text-emerald-700">(otomatis)</span></label><input readonly name="bast_number" value="{{ $purchaseDetails?->bast_number ?: $transaction->bast_number }}" class="mt-1 w-full cursor-not-allowed rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-base text-slate-600" placeholder="Terbit setelah penomoran"></div>
                                        <div><label class="text-xs font-medium text-sky-900">Tanggal BAST <span class="text-rose-600">*</span></label><input required type="date" name="bast_date" value="{{ $bastDate }}" min="{{ $bapDate }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                                    </div>
                                </div>
                                <div data-spj-section="KONSUMSI" x-data="{ rows: @js($participantRows), roster: @js(collect($participantRoster ?? [])->map(fn ($employee) => ['name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type, 'portions' => 1])->values()->all()), participantCount: {{ (int) old('participant_count', $transaction->participant_count ?: collect($participantRows)->sum('portions')) }}, dragIndex: null, get portionTotal() { return this.rows.reduce((total, row) => total + (parseInt(row.portions) || 0), 0); }, fillRoster() { this.rows = this.roster.map(row => ({...row})); this.participantCount = this.portionTotal; }, move(index, direction) { const target = index + direction; if (target < 0 || target >= this.rows.length) return; [this.rows[index], this.rows[target]] = [this.rows[target], this.rows[index]]; this.rows = [...this.rows]; }, dropAt(index) { if (this.dragIndex === null || this.dragIndex === index) return; const [row] = this.rows.splice(this.dragIndex, 1); this.rows.splice(index, 0, row); this.rows = [...this.rows]; this.dragIndex = null; } }" class="rounded-lg border border-sky-200 bg-sky-50/60 p-3">
                                    <div class="flex items-center justify-between gap-3"><div><h3 class="text-xs font-bold text-sky-900">Acara / Daftar Peserta Rapat</h3><p class="mt-0.5 text-[11px] text-sky-700">Urutan peserta dipakai pada daftar hadir.</p></div><div class="flex gap-2"><button type="button" @click="fillRoster()" class="rounded-md border border-sky-300 bg-white px-2.5 py-1.5 text-xs font-bold text-sky-800">Ambil semua pegawai</button><button type="button" @click="rows.push({name:'', position:'', portions:1})" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-bold text-white">+ Tambah peserta</button></div></div>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label class="text-xs font-semibold text-sky-900">Nama Acara/Rapat <span class="text-rose-600">*</span><input required name="event_name" value="{{ old('event_name', $transaction->event_name) }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-base" placeholder="Nama acara / rapat"></label><label class="text-xs font-semibold text-sky-900">Tempat Pelaksanaan <span class="text-rose-600">*</span><input required name="event_location" value="{{ old('event_location', $transaction->event_location) }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-base" placeholder="Tempat pelaksanaan"></label><label class="text-xs font-semibold text-sky-900">Tanggal Kegiatan <span class="text-rose-600">*</span><input required type="date" name="event_date" value="{{ old('event_date', $transaction->event_date?->format('Y-m-d') ?: $transactionDateLimit) }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-base"></label><label class="text-xs font-semibold text-sky-900">Jumlah Peserta <span class="text-rose-600">*</span><input required type="number" min="1" step="1" name="participant_count" x-model.number="participantCount" class="mt-1 w-full rounded-md border px-2.5 py-1.5 text-base" :class="participantCount === portionTotal ? 'border-sky-200' : 'border-rose-400 bg-rose-50'"></label></div>
                                    <p x-show="participantCount !== portionTotal" class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" x-text="'Jumlah peserta harus sama dengan total porsi (' + portionTotal + ').'"></p>
                                    <div class="mt-2 overflow-x-auto"><table class="min-w-full text-base"><thead><tr class="text-left text-[11px] font-bold uppercase text-sky-800"><th class="px-2 py-1.5">No.</th><th class="px-2 py-1.5">Nama Lengkap Peserta / Guru</th><th class="px-2 py-1.5">Jabatan / Instansi</th><th class="px-2 py-1.5">Jumlah Porsi</th><th class="px-2 py-1.5">Aksi</th></tr></thead><tbody><template x-for="(row,index) in rows" :key="index"><tr @dragover.prevent @drop.prevent="dropAt(index)" :class="dragIndex === index ? 'bg-sky-100' : ''"><td class="px-2 py-1.5"><div class="flex items-center gap-2"><button type="button" draggable="true" @dragstart="dragIndex=index; $event.dataTransfer.effectAllowed='move'" @dragend="dragIndex=null" title="Seret untuk mengubah urutan" class="cursor-grab rounded border border-sky-200 bg-white px-1.5 py-1 text-slate-500 active:cursor-grabbing">⋮⋮</button><span x-text="index+1"></span></div></td><td class="px-2 py-1.5"><input required :name="`participants[${index}][name]`" x-model="row.name" class="w-full rounded border border-sky-200 px-2 py-1"></td><td class="px-2 py-1.5"><input :name="`participants[${index}][position]`" x-model="row.position" class="w-full rounded border border-sky-200 px-2 py-1"></td><td class="px-2 py-1.5"><input required type="number" min="1" step="1" :name="`participants[${index}][portions]`" x-model.number="row.portions" class="w-24 rounded border border-sky-200 px-2 py-1 text-right"></td><td class="px-2 py-1.5"><div class="flex gap-1"><button type="button" @click="move(index,-1)" :disabled="index === 0" title="Naikkan urutan" class="rounded border border-sky-200 px-2 py-1 text-sky-700 disabled:opacity-35">↑</button><button type="button" @click="move(index,1)" :disabled="index === rows.length - 1" title="Turunkan urutan" class="rounded border border-sky-200 px-2 py-1 text-sky-700 disabled:opacity-35">↓</button><button type="button" @click="rows.splice(index,1); participantCount = portionTotal" class="text-xs font-bold text-rose-700">Hapus</button></div></td></tr></template></tbody></table></div>
                                </div>
                                <div class="flex justify-end pt-1"><button :disabled="saving" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-1.5 text-base font-bold text-white shadow hover:bg-indigo-700 disabled:opacity-60"><span x-show="saving" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span> <span x-text="saving ? 'Menyimpan...' : 'Simpan Isian Paket'"></span></button></div>
                    </div>
                    </fieldset>
                            </form>
                        </div>

                        <div x-show="packageTab === 'penomoran'" data-panel="penomoran" class="tab-panel p-4">
                            <h2 class="text-base font-bold text-slate-800">Penomoran Dokumen</h2>
                            <p class="mt-1 text-xs text-slate-500">Nomor mengikuti format aktif dan tanggal peristiwa. Nomor yang sudah diterbitkan tidak akan ditimpa.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3"><div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><p class="text-[11px] font-bold uppercase text-slate-500">Kategori</p><p class="mt-1 font-bold text-slate-800">{{ $spjTypeLabel($packageCategory) }}</p></div><div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><p class="text-[11px] font-bold uppercase text-slate-500">Tanggal sumber</p><p class="mt-1 font-bold text-slate-800">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</p></div><div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><p class="text-[11px] font-bold uppercase text-slate-500">Cakupan nomor</p><p class="mt-1 font-bold text-indigo-700">{{ $isHonorPackage ? 'SPJ utama · dipakai pada kuitansi honor' : ($isGoodsPackage ? 'SPJ, Pesanan, BAP, BAST' : 'SPJ dan dokumen kategori') }}</p></div></div>
                            @if($hasActiveSpjNumber)
                                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Sudah ditetapkan</p>
                                    <p class="mt-1 font-mono text-base font-bold text-emerald-800 break-all">{{ $activeSpjDocument->document_number }}</p>
                                </div>
                            @elseif($package->status === 'CANCELLED')
                                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-rose-600">Nomor dibatalkan</p>
                                    <p class="mt-1 break-all font-mono text-base font-bold text-rose-800 line-through">{{ $cancelledSpjDocument?->document_number ?: $package->document_number }}</p>
                                    @if($cancelledSpjDocument?->cancellation_reason)<p class="mt-2 text-xs text-rose-700">Alasan: {{ $cancelledSpjDocument->cancellation_reason }}</p>@endif
                                    <form class="mt-3" method="POST" action="{{ route('spj.assign-number', $package->id) }}" data-confirm="Terbitkan ulang nomor SPJ tanpa mengubah data paket? Sistem akan menggunakan slot nomor tersedia sesuai urutan aktif.">
                                        @csrf
                                        <button class="rounded-md bg-violet-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-violet-700">Terbitkan ulang nomor</button>
                                        <p class="mt-1 text-xs text-rose-700">Gunakan ini jika isian sudah benar. Untuk koreksi data, buka paket terlebih dahulu.</p>
                                    </form>
                                </div>
                            @else
                                <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                    <p class="text-base font-medium text-slate-700">Belum bernomor</p>
                                    <p class="mt-1 text-xs text-slate-500">Klik tombol di bawah untuk generate nomor otomatis.</p>
                                    <form class="mt-4" method="POST" action="{{ route('spj.assign-number', $package->id) }}">@csrf<button class="rounded-md bg-violet-600 px-4 py-2 text-base font-bold text-white shadow hover:bg-violet-700">Terbitkan nomor otomatis</button><p class="mt-2 text-xs text-slate-500">{{ $isHonorPackage ? 'Nomor SPJ dibuat dari tanggal transaksi dan dipakai sebagai referensi kuitansi pembayaran honor.' : 'Nomor dibuat dari tanggal transaksi serta tanggal dokumen kategori yang tersedia.' }}</p></form>
                                </div>
                            @endif
                            @if($package->documents->isNotEmpty())
                                <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                                    @foreach($package->documents as $document)
                                        <div class="p-3"><div class="flex flex-wrap items-center justify-between gap-2"><div><b>{{ $document->document_type }}</b><p class="font-mono text-xs text-slate-600">{{ $document->document_number }}</p></div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ $document->status }}@if($document->is_late_entry) · SUSULAN @endif</span></div>
                                            @if(in_array($document->status, ['NUMBERED', 'FINAL']))
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @if($document->status === 'NUMBERED')<form method="POST" action="{{ route('spj.documents.finalize', $document->id) }}">@csrf<button class="rounded bg-emerald-600 px-2 py-1 text-xs font-bold text-white">Finalkan</button></form>@endif
                                                    <form method="POST" action="{{ route('spj.documents.replace', $document->id) }}" class="flex gap-1" data-confirm="Nomor lama {{ $document->document_number }} akan dibatalkan permanen dan sistem menerbitkan nomor baru. Lanjutkan?">@csrf<input name="reason" required minlength="5" placeholder="Alasan penomoran ulang" class="rounded border-slate-300 text-xs"><button class="rounded bg-amber-600 px-2 py-1 text-xs font-bold text-white hover:bg-amber-700">Nomori ulang</button></form>
                                                    @if(auth()->user()->isAdministrator())
                                                        <form method="POST" action="{{ route('spj.documents.cancel', $document->id) }}" class="flex gap-1" data-confirm="Batalkan nomor {{ $document->document_number }}? Slot nomor dapat dipakai kembali sesuai aturan urutan.">@csrf<input name="reason" required minlength="5" placeholder="Alasan pembatalan" class="rounded border-rose-300 text-xs"><button class="rounded bg-rose-600 px-2 py-1 text-xs font-bold text-white hover:bg-rose-700">Batalkan nomor</button></form>
                                                    @endif
                                                </div>
                                            @elseif($document->status === 'CANCELLED')
                                                <div class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800"><b>Nomor dibatalkan.</b> {{ $document->cancellation_reason }}</div>
                                                @if($document->document_type === 'SPJ' && $document->scope_key === 'MAIN' && $package->status === 'DRAFT')
                                                    <form method="POST" action="{{ route('spj.assign-number', $package->id) }}" class="mt-2" data-confirm="Terbitkan nomor SPJ baru sebagai pengganti nomor yang dibatalkan?">
                                                        @csrf
                                                        <button class="rounded bg-violet-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-violet-700">Terbitkan nomor pengganti</button>
                                                        <p class="mt-1 text-xs text-slate-500">Nomor lama tetap tersimpan dalam riwayat dan tidak digunakan kembali.</p>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if($package->status === 'CANCELLED' && auth()->user()->isAdministrator())
                                <form method="POST" action="{{ route('spj.unlock', $package->id) }}" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">@csrf<label class="text-xs font-bold text-amber-900">Buka paket untuk koreksi input</label><div class="mt-2 flex gap-2"><input name="reason" required minlength="5" placeholder="Alasan pembukaan paket" class="min-w-0 flex-1 rounded border-amber-300 text-sm"><button class="rounded bg-amber-600 px-3 py-2 text-xs font-bold text-white hover:bg-amber-700">Buka paket</button></div></form>
                            @endif
                            @unless($isHonorPackage)<fieldset @disabled(!$package->isEditable()) class="mt-5 disabled:cursor-not-allowed disabled:opacity-60"><div class="grid gap-4 {{ $isGoodsPackage ? 'lg:grid-cols-2' : '' }}">
                                @if($isGoodsPackage)<section class="rounded-lg border border-slate-200 p-4">
                                    <h3 class="font-bold text-slate-800">Pembayaran bertahap</h3>
                                    <p class="mt-1 text-xs text-slate-500">Total bruto tidak boleh melampaui nilai transaksi.</p>
                                    <div class="mt-3 space-y-2">@forelse($transaction->payments as $payment)<div class="flex justify-between text-sm"><span>{{ $payment->payment_date?->translatedFormat('d F Y') }} · {{ $payment->scope_key }} @if($payment->is_late_entry)<b class="text-amber-700">Susulan</b>@endif</span><b>{{ $rupiah($payment->gross_amount) }}</b></div>@empty<p class="text-xs text-slate-400">Belum ada pembayaran.</p>@endforelse</div>
                                    <form method="POST" action="{{ route('spj.payments.store', $transaction->id) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
                                        <input type="date" name="payment_date" required class="rounded-md border-slate-300 text-sm"><input type="number" name="gross_amount" min="1" required placeholder="Nilai bruto" class="rounded-md border-slate-300 text-sm">
                                        <input type="number" name="tax_amount" min="0" value="0" placeholder="Pajak" class="rounded-md border-slate-300 text-sm"><input name="payment_reference" placeholder="Referensi pembayaran" class="rounded-md border-slate-300 text-sm">
                                        <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-bold text-white sm:col-span-2">Tambah pembayaran</button>
                                    </form>
                                </section>
                                <section class="rounded-lg border border-slate-200 p-4">
                                    <h3 class="font-bold text-slate-800">Penerimaan barang bertahap</h3>
                                    <p class="mt-1 text-xs text-slate-500">Jumlah kumulatif tidak boleh melebihi jumlah pesanan.</p>
                                    <div class="mt-3 space-y-2">@forelse($transaction->goodsReceipts as $receipt)<div class="text-sm"><b>{{ $receipt->scope_key }}</b> · {{ $receipt->receipt_date?->translatedFormat('d F Y') }} @if($receipt->is_late_entry)<b class="text-amber-700">Susulan</b>@endif</div>@empty<p class="text-xs text-slate-400">Belum ada penerimaan bertahap.</p>@endforelse</div>
                                    <form method="POST" action="{{ route('spj.receipts.store', $transaction->id) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
                                        <input type="date" name="receipt_date" required class="rounded-md border-slate-300 text-sm">
                                        <select name="items[0][transaction_item_id]" required class="rounded-md border-slate-300 text-sm"><option value="">Pilih barang</option>@foreach($transaction->items as $item)<option value="{{ $item->id }}">{{ $item->item_description ?: $item->description }}</option>@endforeach</select>
                                        <input type="number" step="0.0001" min="0.0001" name="items[0][quantity_received]" required placeholder="Jumlah diterima" class="rounded-md border-slate-300 text-sm"><input type="number" min="0" name="items[0][amount_received]" placeholder="Nilai penerimaan" class="rounded-md border-slate-300 text-sm">
                                        <button class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-bold text-white sm:col-span-2">Tambah penerimaan</button>
                                    </form>
                                </section>@endif
                            </div></fieldset>@endunless
                        </div>
                    </section>
                @else
                    @php($listedPackages = $packageList ?? collect())
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="font-bold text-slate-900">Daftar Paket SPJ</h2>
                        <p class="mt-1 text-sm text-slate-500">Pilih paket untuk memeriksa kelengkapan, memperbaiki isian manual, dan mengelola penomoran.</p>
                    </div>
                    <div class="grid gap-3 p-4 lg:hidden">
                        @forelse($listedPackages as $listedPackage)
                            <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id]) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-sm font-bold theme-text">{{ $listedPackage->transaction->no_bukti }}</p><p class="mt-1 text-xs text-slate-500">{{ $listedPackage->transaction->transaction_date?->translatedFormat('d F Y') }}</p></div><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $listedPackage->status === 'CANCELLED' ? 'bg-rose-50 text-rose-700' : ($listedPackage->document_number ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700') }}">{{ $listedPackage->status === 'CANCELLED' ? 'Dibatalkan' : ($listedPackage->document_number ? 'Bernomor' : 'Draft') }}</span></div>
                                <p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-800">{{ $listedPackage->transaction->payment_description ?: $listedPackage->transaction->description }}</p>
                                <div class="mt-3 flex items-center justify-between text-xs text-slate-500"><span>{{ $spjTypeLabel($listedPackage->transaction->spj_category) }}</span><b class="text-slate-800">{{ $rupiah($listedPackage->transaction->gross_amount) }}</b></div>
                            </a>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</div>
                        @endforelse
                    </div>
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">Bukti / Tanggal</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Uraian / Penerima</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Kategori</th><th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Nilai</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Status</th><th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-500">Aksi</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($listedPackages as $listedPackage)
                                    <tr class="hover:bg-slate-50"><td class="px-5 py-4"><p class="font-mono font-bold theme-text">{{ $listedPackage->transaction->no_bukti }}</p><p class="mt-1 text-xs text-slate-500">{{ $listedPackage->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td><td class="max-w-sm px-4 py-4"><p class="truncate font-semibold text-slate-800">{{ $listedPackage->transaction->payment_description ?: $listedPackage->transaction->description }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $listedPackage->transaction->recipient_name ?: 'Penerima belum diisi' }}</p></td><td class="px-4 py-4 text-xs font-bold theme-text">{{ $spjTypeLabel($listedPackage->transaction->spj_category) }}</td><td class="px-4 py-4 text-right font-semibold text-slate-800">{{ $rupiah($listedPackage->transaction->gross_amount) }}</td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $listedPackage->status === 'CANCELLED' ? 'bg-rose-50 text-rose-700' : ($listedPackage->document_number ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700') }}">{{ $listedPackage->status === 'CANCELLED' ? 'Dibatalkan' : ($listedPackage->document_number ? 'Bernomor' : 'Draft paket') }}</span>@if($listedPackage->document_number)<p class="mt-1 font-mono text-[11px] text-slate-500">{{ $listedPackage->document_number }}</p>@endif</td><td class="px-5 py-4 text-right"><a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id]) }}" class="inline-flex rounded-lg theme-bg px-3 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90">Buka paket →</a></td></tr>
                                @empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($packageList) && $packageList->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $packageList->links() }}</div>@endif
                @endif
            </div>

            {{-- Tab: Laporan --}}
            <div x-show="tab === 'laporan'" x-transition>
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <form method="GET" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="tab" value="laporan">
                        <div><label class="text-xs font-bold text-slate-500">BULAN</label><select name="month" class="mt-1 block rounded-lg border-slate-300 text-base"><option value="">Semua bulan</option>@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(request('month') == $month)>{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>@endforeach</select></div>
                        <div><label class="text-xs font-bold text-slate-500">TRIWULAN</label><select name="quarter" class="mt-1 block rounded-lg border-slate-300 text-base"><option value="">Semua triwulan</option>@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}" @selected(request('quarter') == $quarter)>Triwulan {{ $quarter }}</option>@endforeach</select></div>
                        <div><label class="text-xs font-bold text-slate-500">SEMESTER</label><select name="semester" class="mt-1 block rounded-lg border-slate-300 text-base"><option value="">Semua semester</option><option value="1" @selected(request('semester') == 1)>Semester 1</option><option value="2" @selected(request('semester') == 2)>Semester 2</option></select></div>
                        <button class="rounded-lg bg-violet-600 px-4 py-2.5 text-base font-bold">Terapkan</button>
                        <a href="{{ route('spj.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-base font-bold text-rose-700">Pratinjau PDF</a>
                        <a href="{{ route('spj.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-base font-bold text-emerald-700">Unduh Excel</a>
                        <a href="{{ route('spj.honor-payments.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-2.5 text-base font-bold text-violet-700">Daftar Honor PDF</a>
                        <a href="{{ route('spj.honor-payments.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-base font-bold text-sky-700">Daftar Honor Excel</a>
                    </form>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">@foreach([['Paket sukses',$summary['count'] ?? 0,'text-indigo-700'],['Paket dibatalkan',$summary['cancelled_count'] ?? 0,'text-rose-700'],['Nilai bruto sukses',$rupiah($summary['gross'] ?? 0),'text-slate-800'],['Nilai dibayarkan sukses',$rupiah($summary['net'] ?? 0),'text-emerald-700']] as [$label,$value,$color])<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow hover:shadow transition"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-2 text-xl font-bold {{ $color }}">{{ $value }}</p></div>@endforeach</div>
                <div class="overflow-x-auto p-5"><table class="min-w-full divide-y divide-slate-200 text-base"><thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">NOMOR SPJ</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">STATUS</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">BUKTI / TANGGAL</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">PENERIMA</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">BRUTO</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">PAJAK</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">DIBAYARKAN</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($packages ?? [] as $package)@php($isCancelled = $package->report_status === 'CANCELLED')<tr class="transition {{ $isCancelled ? 'bg-rose-50/70 text-slate-500' : 'hover:bg-indigo-50/40' }}"><td class="px-4 py-3 font-mono text-xs font-bold {{ $isCancelled ? 'text-rose-700 line-through' : 'text-indigo-700' }}"><a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="hover:underline">{{ $package->report_document_number }}</a></td><td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isCancelled ? 'border border-rose-200 bg-rose-100 text-rose-800' : 'border border-emerald-200 bg-emerald-100 text-emerald-800' }}">{{ $isCancelled ? 'Dibatalkan' : 'Sukses' }}</span>@if($isCancelled && $package->report_cancellation_reason)<p class="mt-1 max-w-48 text-xs text-rose-700">{{ $package->report_cancellation_reason }}</p>@endif</td><td class="px-4 py-3"><p class="font-semibold">{{ $package->transaction->no_bukti }}</p><p class="text-xs text-slate-500">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td><td class="px-4 py-3">{{ $package->transaction->recipient_name }}</td><td class="px-4 py-3 text-right">{{ $rupiah($package->transaction->gross_amount) }}</td><td class="px-4 py-3 text-right {{ $isCancelled ? 'text-slate-400' : 'text-amber-700' }}">{{ $rupiah($package->transaction->tax_total) }}</td><td class="px-4 py-3 text-right font-bold {{ $isCancelled ? 'text-slate-400' : 'text-emerald-700' }}">{{ $rupiah($package->transaction->net_amount) }}</td></tr>@empty<tr><td colspan="7" class="px-5 py-14 text-center text-slate-500">Belum ada riwayat paket SPJ untuk filter ini.</td></tr>@endforelse</tbody></table></div>
            </div>

            {{-- Tab: Monitoring --}}
            <div x-show="tab === 'monitoring'" x-transition>
                <div class="border-b border-amber-100 bg-amber-50/40 px-5 py-4 sm:px-6">
                    <div><h2 class="font-bold text-amber-900">Monitoring Dokumen Belum Lengkap</h2><p class="mt-1 text-base text-amber-800">Transaksi ber-rincian tapi paket belum siap atau belum bernomor · <span class="font-bold">{{ $pendingPaginator?->total() ?? 0 }} transaksi</span></p></div>
                    @if(auth()->user()?->isAdministrator())
                        <form method="POST" action="{{ route('spj.quarter-numbering') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-indigo-200 bg-white p-3" data-confirm="Rekonsiliasi nomor triwulan ini? Transaksi yang sudah memiliki nomor aktif akan dilewati dan slot nomor yang dibatalkan dapat dipakai dokumen berikutnya dalam domain serta periode yang sama.">
                            @csrf
                            <div><label class="block text-xs font-bold text-slate-600">TRIWULAN SIAP DINOMORI</label><select name="quarter" class="mt-1 rounded-md border-slate-300 text-base">@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>@endforeach</select></div>
                            <button class="rounded-md bg-indigo-600 px-4 py-2 text-base font-bold text-white hover:bg-indigo-700">Rekonsiliasi nomor triwulan</button>
                            <p class="basis-full text-xs text-slate-500">Nomor aktif dipertahankan. Slot nomor batal dipakai kembali menurut urutan terkecil oleh dokumen berikutnya dalam jenis dan periode penomoran yang sama.</p>
                            <p class="basis-full text-xs text-slate-500">Setiap jenis dokumen diurutkan menurut tanggal peristiwanya. Nomor yang sudah terbit akan dilewati.</p>
                        </form>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach(range(1,4) as $quarter)
                                @php($period = ($periodClosures ?? collect())->get($quarter))
                                <div class="rounded-lg border border-slate-200 bg-white p-3"><div class="flex items-center justify-between"><b>Triwulan {{ $quarter }}</b><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ $period?->status ?? 'OPEN' }}</span></div>
                                    @if($period?->status === 'NUMBERED')<form method="POST" action="{{ route('spj.quarter-close') }}" class="mt-2">@csrf<input type="hidden" name="quarter" value="{{ $quarter }}"><button class="w-full rounded-md bg-slate-800 px-3 py-1.5 text-xs font-bold text-white">Tutup triwulan</button></form>@endif
                                    @if($period?->status === 'CLOSED')<form method="POST" action="{{ route('spj.quarter-reopen', $period->id) }}" class="mt-2 space-y-2">@csrf<input name="reason" required placeholder="Alasan pembukaan" class="w-full rounded-md border-slate-300 text-xs"><button class="w-full rounded-md bg-amber-600 px-3 py-1.5 text-xs font-bold text-white">Buka kembali</button></form>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="overflow-x-auto p-5"><table class="min-w-full divide-y divide-amber-100 text-base"><thead class="bg-amber-50"><tr><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">BUKTI</th><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">URAIAN</th><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">STATUS</th><th class="px-4 py-3 text-right text-xs font-bold text-amber-800">AKSI</th></tr></thead><tbody class="divide-y divide-amber-100">@forelse($pendingPaginator ?? [] as $transaction)@php($wasCancelled = $transaction->spjPackage?->documents?->contains('status', 'CANCELLED') ?? false)<tr class="transition {{ $wasCancelled ? 'bg-rose-50/60 hover:bg-rose-50' : 'hover:bg-amber-50/60' }}"><td class="px-4 py-3 font-mono font-bold {{ $wasCancelled ? 'text-rose-800' : 'text-amber-900' }}">{{ $transaction->no_bukti }}</td><td class="px-4 py-3 max-w-sm truncate">{{ $transaction->description }}</td><td class="px-4 py-3"><span class="rounded-full border px-2 py-0.5 text-xs font-bold {{ $wasCancelled ? 'border-rose-200 bg-rose-100 text-rose-800' : ($transaction->spjPackage ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-slate-200 bg-slate-100 text-slate-500') }}">{{ $wasCancelled ? 'Dibatalkan — menunggu nomor baru' : ($transaction->spjPackage ? 'Draft — nomor belum ditetapkan' : 'Paket belum disiapkan') }}</span></td><td class="px-4 py-3 text-right">@if($transaction->spjPackage)<a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}" class="font-bold text-indigo-700 hover:underline">{{ $wasCancelled ? 'Periksa paket →' : 'Lengkapi paket →' }}</a>@else<a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="font-bold text-indigo-700 hover:underline">Buka persiapan →</a>@endif</td></tr>@empty<tr><td colspan="4" class="px-5 py-10 text-center text-emerald-700">Tidak ada transaksi ber-rincian yang tertunda.</td></tr>@endforelse</tbody></table></div>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const category = document.getElementById('spj-type');
            const refresh = () => {
                const value = (category?.value || '').toUpperCase();
                document.querySelectorAll('[data-spj-section]').forEach((section) => {
                    const allowed = section.dataset.spjSection.split(' ');
                    section.classList.toggle('hidden', !allowed.includes(value));
                });
            };
            category?.addEventListener('change', refresh);
            refresh();
        })();
    </script>
</x-layouts.tailwind-app>
