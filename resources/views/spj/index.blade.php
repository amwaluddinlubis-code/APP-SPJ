<x-layouts.tailwind-app>
    @php
        $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
            'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
            default => str_replace('_', ' ', (string) $value),
        };
        $spjProgress = ($totalPackages ?? 0) > 0 ? min(100, (int) round((($numberedPackages ?? 0) / $totalPackages) * 100)) : 0;
        $packagesAwaitingNumber = max(0, ($totalPackages ?? 0) - ($numberedPackages ?? 0));
        $transactionsWithoutPackage = max(0, ($readyTransactions ?? 0) - ($totalPackages ?? 0));
    @endphp
    <div class="spj-semantic-workspace space-y-6" x-data="{
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
        <x-page-header
            title="Pusat Dokumen SPJ"
            subtitle="Kelola alur dari transaksi siap, kelengkapan paket, hingga dokumen bernomor dalam satu ruang kerja."
            kicker="MANAJEMEN DOKUMEN PERTANGGUNGJAWABAN"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('transactions.index')">Lihat transaksi</x-ui.button>
                <x-ui.button type="button" data-tab="monitoring">Periksa kendala</x-ui.button>
            </x-slot:actions>

            @include('spj.partials.summary')
        </x-page-header>

        {{-- Tab Navigation --}}
        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
            @include('spj.partials.main-tabs')

            {{-- Tab: Persiapan --}}
            @include('spj.partials.preparation')
            {{-- Tab: Paket --}}
            <div x-show="tab === 'paket'" x-transition>
                @php
                    if (isset($package)) {
                @endphp
                    @php
                        $transaction = $package->transaction;
                        $participantRows = $transaction->participants->map(fn ($participant) => ['name' => $participant->name, 'position' => $participant->position, 'nip' => $participant->nip, 'nuptk' => $participant->nuptk, 'portions' => (int) $participant->portions])->values()->all();
                    @endphp
                    @if(strtoupper((string) $transaction->spj_category) === 'KONSUMSI' && $participantRows === [])
                        @php
                            $participantRows = collect($participantRoster ?? [])->map(fn ($employee) => ['name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type, 'nip' => $employee->nip, 'nuptk' => $employee->nuptk, 'portions' => 1])->values()->all();
                        @endphp
                    @endif
                    @php
                        $participantRows = old('participants', $participantRows);
                        $activeSpjDocument = $package->documents->first(fn ($document) => $document->document_type === 'SPJ' && $document->scope_key === 'MAIN' && in_array($document->status, ['NUMBERED', 'FINAL'], true) && filled($document->document_number));
                        $cancelledSpjDocument = $package->documents->where('document_type', 'SPJ')->where('scope_key', 'MAIN')->where('status', 'CANCELLED')->sortByDesc('id')->first();
                        $hasActiveSpjNumber = $activeSpjDocument !== null && $package->status !== 'CANCELLED';
                        $packageCategory = strtoupper((string) $transaction->spj_category);
                        $isHonorPackage = in_array($packageCategory, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true);
                        $isGoodsPackage = in_array($packageCategory, ['BARANG', 'KONSUMSI'], true);
                        $isConsumptionPackage = $packageCategory === 'KONSUMSI';
                        $isSiplah = (bool) $transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah';
                        $purchaseDetails = $transaction->goods->first();
                        $transactionDateLimit = $transaction->transaction_date?->format('Y-m-d');
                        $orderDate = $purchaseDetails?->order_date?->format('Y-m-d') ?: $transaction->order_date?->format('Y-m-d') ?: $transactionDateLimit;
                        $bapDate = $purchaseDetails?->bap_date?->format('Y-m-d') ?: $transaction->bap_date?->format('Y-m-d') ?: $transactionDateLimit;
                        $bastDate = $purchaseDetails?->bast_date?->format('Y-m-d') ?: $transaction->bast_date?->format('Y-m-d') ?: $transactionDateLimit;
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
                        <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'persiapan'])">← Semua paket</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('transactions.show', $transaction->id)">Lihat transaksi</x-ui.button>
                    </div>

                    @include('spj.partials.package.transaction-summary')
                    @if(strtolower((string) $transaction->payment_method) === 'siplah' || $transaction->is_siplah)
                        <section class="mx-5 mt-5 rounded-xl border p-4 shadow-sm" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-base font-bold" style="color: var(--ui-fg)">Metode Pembelian: SiPLah</h2><x-ui.badge>Pembelian SiPLah</x-ui.badge></div>
                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                @foreach([
                                    'Penyedia' => $transaction->vendor_name,
                                    'Nomor Pesanan SiPLah' => $transaction->siplah_order_number,
                                    'Nomor Invoice' => $transaction->invoice_number,
                                    'Tanggal Invoice' => $transaction->invoice_date?->translatedFormat('d F Y'),
                                    'Referensi Pembayaran' => $transaction->payment_reference,
                                ] as $label => $value)
                                    @if(filled($value))<div><dt class="text-xs font-semibold" style="color: var(--ui-fg-muted)">{{ $label }}</dt><dd class="mt-1 font-semibold" style="color: var(--ui-fg)">{{ $value }}</dd></div>@endif
                                @endforeach
                            </dl>
                        </section>
                    @endif

                    @include('spj.partials.package.validation')
                    @include('spj.partials.package.documents')
                    <section class="mx-5 mt-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow" x-data="{ packageTab: new URLSearchParams(window.location.search).get('package_tab') || 'rincian', selectPackageTab(name) { this.packageTab = name; const url = new URL(window.location.href); url.searchParams.set('package_tab', name); window.history.replaceState({}, '', url); } }">
                        <div class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)]">
                            <nav class="flex gap-1 overflow-x-auto px-2 py-1 text-base" role="tablist" aria-label="Bagian Paket SPJ" @click="const button = $event.target.closest('[data-package-tab]'); if (button) selectPackageTab(button.dataset.packageTab)" @keydown="if ($event.key === 'ArrowRight' || $event.key === 'ArrowLeft') { const buttons = [...$el.querySelectorAll('[data-package-tab]')]; const current = buttons.indexOf($event.target); const next = $event.key === 'ArrowRight' ? (current + 1) % buttons.length : (current - 1 + buttons.length) % buttons.length; buttons[next].focus(); selectPackageTab(buttons[next].dataset.packageTab); }">
                                <button type="button" role="tab" id="package-tab-rincian" aria-controls="package-panel-rincian" data-package-tab="rincian" :aria-selected="(packageTab === 'rincian').toString()" :data-active="packageTab === 'rincian'" class="whitespace-nowrap rounded-md border border-transparent px-3 py-2 text-base font-bold text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg)] data-[active=true]:border-[var(--ui-line-strong)] data-[active=true]:bg-[var(--ui-surface-base)] data-[active=true]:text-[var(--theme-content-accent)] data-[active=true]:shadow">📦 Rincian <span class="ml-1 rounded-full bg-[var(--ui-surface-muted)] px-1.5 py-0.5 text-[11px]">{{ $transaction->items->count() }}</span></button>
                                <button type="button" role="tab" id="package-tab-isian" aria-controls="package-panel-isian" data-package-tab="isian" :aria-selected="(packageTab === 'isian').toString()" :data-active="packageTab === 'isian'" class="whitespace-nowrap rounded-md border border-transparent px-3 py-2 text-base font-bold text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg)] data-[active=true]:border-[var(--ui-line-strong)] data-[active=true]:bg-[var(--ui-surface-base)] data-[active=true]:text-[var(--theme-content-accent)] data-[active=true]:shadow">✏️ Isian Manual</button>
                                <button type="button" role="tab" id="package-tab-pajak" aria-controls="package-panel-pajak" data-package-tab="pajak" :aria-selected="(packageTab === 'pajak').toString()" :data-active="packageTab === 'pajak'" class="whitespace-nowrap rounded-md border border-transparent px-3 py-2 text-base font-bold text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg)] data-[active=true]:border-[var(--ui-line-strong)] data-[active=true]:bg-[var(--ui-surface-base)] data-[active=true]:text-[var(--theme-content-accent)] data-[active=true]:shadow">🧾 Rincian Pajak</button>
                                <button type="button" role="tab" id="package-tab-penomoran" aria-controls="package-panel-penomoran" data-package-tab="penomoran" :aria-selected="(packageTab === 'penomoran').toString()" :data-active="packageTab === 'penomoran'" class="whitespace-nowrap rounded-md border border-transparent px-3 py-2 text-base font-bold text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg)] data-[active=true]:border-[var(--ui-line-strong)] data-[active=true]:bg-[var(--ui-surface-base)] data-[active=true]:text-[var(--theme-content-accent)] data-[active=true]:shadow">🔢 Penomoran @if($hasActiveSpjNumber)<span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] text-emerald-700">OK</span>@elseif($package->status === 'CANCELLED')<span class="ml-1 rounded-full bg-rose-100 px-1.5 py-0.5 text-[11px] text-rose-700">Dibatalkan</span>@else<span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-700">Belum</span>@endif</button>
                            </nav>
                        </div>

                        <div x-show="packageTab === 'rincian'" id="package-panel-rincian" role="tabpanel" aria-labelledby="package-tab-rincian" data-panel="rincian" class="tab-panel">
                            @include('spj.partials.package.items-readonly')
                        </div>

                        <div x-show="packageTab === 'isian'" id="package-panel-isian" role="tabpanel" aria-labelledby="package-tab-isian" data-panel="isian" class="tab-panel" x-data="{saving:false}">
                            <div class="border-b border-[var(--ui-line)] px-4 py-3">
                                <h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Isian Manual Paket SPJ</h2>
                                <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Hanya isian kuning yang wajib. Bagian biru tampil sesuai kategori.</p>
                            </div>
                            @php
                                $workDetails = $transaction->workOrder;
                                $workerRows = $transaction->workers->map(fn ($worker) => [
                                    'name' => $worker->name,
                                    'job_description' => $worker->job_description,
                                    'work_days' => $worker->work_days,
                                    'daily_rate' => $worker->daily_rate,
                                    'is_receipt_recipient' => (bool) $worker->is_receipt_recipient,
                                    'notes' => $worker->notes,
                                ])->values()->all();
                                $workerRows = old('spj_category', $transaction->spj_category) === 'PEMELIHARAAN' ? old('workers', $workerRows) : $workerRows;
                                $selectedSpjType = strtoupper((string) old('spj_category', $transaction->spj_category ?: $transaction->spj_category));
                            @endphp
                            <form id="spj-manual-form" method="POST" action="{{ route('spj.update', $package->id) }}" data-source-siplah="{{ $transaction->is_siplah ? '1' : '0' }}" class="space-y-4 p-4" @submit="if (!$event.defaultPrevented) saving=true">@csrf @method('PUT')
                    @unless($package->isEditable())<div class="flex items-start gap-2 rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-muted)] px-3 py-2 text-sm text-[var(--ui-fg)]"><span aria-hidden="true">🔒</span><p><strong>Isian terkunci.</strong> Batalkan nomor dan buka paket untuk koreksi agar field dapat diedit kembali.</p></div>@endunless
                    <fieldset @disabled(!$package->isEditable()) class="disabled:cursor-not-allowed disabled:opacity-60">
                    <div x-show="saving" class="flex items-center justify-center py-4"><x-loading-spinner /></div>
                    <div x-show="!saving">
                                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                                    <div class="grid gap-3">
                                        <div>
                                            <label class="text-xs font-bold text-amber-900">Kategori SPJ <span class="text-rose-600">*</span></label>
                                            <x-ui.select id="spj-type" name="spj_category" class="mt-1">
                                                <option value="">Pilih kategori</option>
                                                @foreach(['BARANG','KONSUMSI','PEMELIHARAAN','JASA_LAINNYA','SPPD','HONOR_PEGAWAI'] as $value)
                                                    <option value="{{ $value }}" @selected(in_array($selectedSpjType, ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) && in_array(strtoupper((string) $value), ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) || old('spj_category', $transaction->spj_category ?: $transaction->spj_category) === $value)>{{ $spjTypeLabel($value) }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </div>
                                        <p class="text-xs text-amber-800">Kategori menentukan field manual, dokumen pendukung, dan nomor yang diterbitkan. Subkategori terpisah tidak diperlukan.</p>
                                    </div>
                                </div>
                                @include('spj.partials.package.common')
                                @include('spj.partials.package.categories.honor-pegawai')
                                @include('spj.partials.package.categories.sppd')
                                @include('spj.partials.package.categories.barang')
@include('spj.partials.package.categories.konsumsi')
@include('spj.partials.package.categories.pemeliharaan')
                                @include('spj.partials.package.categories.jasa-lainnya')
                                <div class="flex justify-end pt-1"><x-ui.button type="submit" x-bind:disabled="saving" class="px-4 py-1.5 text-base"><span x-show="saving" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span> <span x-text="saving ? 'Menyimpan...' : 'Simpan Isian Paket'"></span></x-ui.button></div>
                    </div>
                    </fieldset>
                            </form>
                        </div>

                        <div x-show="packageTab === 'pajak'" id="package-panel-pajak" role="tabpanel" aria-labelledby="package-tab-pajak" data-panel="pajak" class="tab-panel p-4">
                            @include('spj.partials.package.tax-reference')
                        </div>

                        <div x-show="packageTab === 'penomoran'" id="package-panel-penomoran" role="tabpanel" aria-labelledby="package-tab-penomoran" data-panel="penomoran" class="tab-panel p-4">
                            @include('spj.partials.package.numbering')
                        </div>
                    </section>
                @php
                    } else {
                @endphp
                    @php
                        $listedPackages = $packageList ?? collect();
                    @endphp
                    <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
                        <h2 class="font-bold" style="color: var(--ui-fg)">Daftar Paket SPJ</h2>
                        <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Pilih paket untuk memeriksa kelengkapan, memperbaiki isian manual, dan mengelola penomoran.</p>
                    </div>
                    <div class="grid gap-3 p-4 lg:hidden">
                        @forelse($listedPackages as $listedPackage)
                            <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id]) }}" class="rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" style="border-color: var(--ui-line); background: var(--ui-surface-base); color: var(--ui-fg)">
                                <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-sm font-bold theme-text">{{ $listedPackage->transaction->no_bukti }}</p><p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $listedPackage->transaction->transaction_date?->translatedFormat('d F Y') }}</p></div>@if($listedPackage->status === 'CANCELLED')<x-ui.status-badge status="CANCELLED" label="Dibatalkan" size="xs" />@elseif($listedPackage->document_number)<x-ui.status-badge status="NUMBERED" label="Bernomor" size="xs" />@else<x-ui.status-badge status="DRAFT" label="Draft" size="xs" />@endif</div>
                                <p class="mt-2 line-clamp-2 text-sm font-semibold" style="color: var(--ui-fg)">{{ $listedPackage->transaction->payment_description ?: $listedPackage->transaction->description }}</p>
                                <div class="mt-3 flex items-center justify-between text-xs" style="color: var(--ui-fg-muted)"><span style="color: var(--theme-content-accent)">{{ $spjTypeLabel($listedPackage->transaction->spj_category) }}</span><b style="color: var(--ui-fg)">{{ $rupiah($listedPackage->transaction->gross_amount) }}</b></div>
                            </a>
                        @empty
                            <div class="rounded-xl border border-dashed p-8 text-center" style="border-color: var(--ui-line); color: var(--ui-fg-muted)">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</div>
                        @endforelse
                    </div>
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                            <thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">Bukti / Tanggal</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Uraian / Penerima</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Kategori</th><th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Nilai</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Status</th><th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-500">Aksi</th></tr></thead>
                            <tbody class="divide-y divide-[var(--ui-line)]">
                                @forelse($listedPackages as $listedPackage)
                                    <tr class="hover:bg-slate-50"><td class="px-5 py-4"><p class="font-mono font-bold theme-text">{{ $listedPackage->transaction->no_bukti }}</p><p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $listedPackage->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td><td class="max-w-sm px-4 py-4"><p class="truncate font-semibold" style="color: var(--ui-fg)">{{ $listedPackage->transaction->payment_description ?: $listedPackage->transaction->description }}</p><p class="mt-1 truncate text-xs" style="color: var(--ui-fg-muted)">{{ $listedPackage->transaction->recipient_name ?: 'Penerima belum diisi' }}</p></td><td class="px-4 py-4 text-xs font-bold" style="color: var(--theme-content-accent)">{{ $spjTypeLabel($listedPackage->transaction->spj_category) }}</td><td class="px-4 py-4 text-right font-semibold" style="color: var(--ui-fg)">{{ $rupiah($listedPackage->transaction->gross_amount) }}</td><td class="px-4 py-4">@if($listedPackage->status === 'CANCELLED')<x-ui.status-badge status="CANCELLED" label="Dibatalkan" />@elseif($listedPackage->document_number)<x-ui.status-badge status="NUMBERED" label="Bernomor" />@else<x-ui.status-badge status="DRAFT" label="Draft paket" />@endif @if($listedPackage->document_number)<p class="mt-1 font-mono text-[11px]" style="color: var(--ui-fg-muted)">{{ $listedPackage->document_number }}</p>@endif</td><td class="px-5 py-4 text-right"><x-ui.button :href="route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id])">Buka paket →</x-ui.button></td></tr>
                                @empty<tr><td colspan="6" class="px-5 py-12 text-center" style="color: var(--ui-fg-muted)">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($packageList) && $packageList->hasPages())<div class="border-t border-[var(--ui-line)] px-5 py-4">{{ $packageList->links() }}</div>@endif
                @php
                    }
                @endphp
            </div>

            {{-- Tab: Laporan --}}
            <div x-show="tab === 'laporan'" x-transition>
                <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
                    <form method="GET" class="spj-report-toolbar flex flex-wrap items-end gap-3">
                        <input type="hidden" name="tab" value="laporan">
                        <x-ui.field label="Bulan"><x-ui.select name="month"><option value="">Semua bulan</option>@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(request('month') == $month)>{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>@endforeach</x-ui.select></x-ui.field>
                        <x-ui.field label="Triwulan"><x-ui.select name="quarter"><option value="">Semua triwulan</option>@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}" @selected(request('quarter') == $quarter)>Triwulan {{ $quarter }}</option>@endforeach</x-ui.select></x-ui.field>
                        <x-ui.field label="Semester"><x-ui.select name="semester"><option value="">Semua semester</option><option value="1" @selected(request('semester') == 1)>Semester 1</option><option value="2" @selected(request('semester') == 2)>Semester 2</option></x-ui.select></x-ui.field>
                        <x-ui.button type="submit">Terapkan</x-ui.button>
                        <a href="{{ route('spj.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-base font-bold text-rose-700">Pratinjau PDF</a>
                        <a href="{{ route('spj.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-base font-bold text-emerald-700">Unduh Excel</a>
                        <x-ui.button variant="secondary" :href="route('spj.honor-payments.export', array_merge(request()->query(), ['format' => 'pdf']))" target="_blank">Daftar Honor PDF</x-ui.button>
                        <a href="{{ route('spj.honor-payments.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-base font-bold text-sky-700">Daftar Honor Excel</a>
                    </form>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">@foreach([['Paket sukses',$summary['count'] ?? 0,'text-indigo-700'],['Paket dibatalkan',$summary['cancelled_count'] ?? 0,'text-rose-700'],['Nilai bruto sukses',$rupiah($summary['gross'] ?? 0),'text-slate-800'],['Nilai dibayarkan sukses',$rupiah($summary['net'] ?? 0),'text-emerald-700']] as [$label,$value,$color])<div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow hover:shadow transition"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-2 text-xl font-bold {{ $color }}">{{ $value }}</p></div>@endforeach</div>
                <div class="overflow-x-auto p-5"><table class="min-w-full divide-y divide-[var(--ui-line)] text-base"><thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">NOMOR SPJ</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">STATUS</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">BUKTI / TANGGAL</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">PENERIMA</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">BRUTO</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">PAJAK</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">DIBAYARKAN</th></tr></thead><tbody class="divide-y divide-[var(--ui-line)]">@php
                    $isCancelled = false;
                @endphp
                @forelse($packages ?? [] as $package)@php
                    $isCancelled = $package->report_status === 'CANCELLED';
                @endphp<tr class="transition {{ $isCancelled ? 'bg-rose-50/70 text-slate-500' : 'hover:bg-indigo-50/40' }}"><td class="px-4 py-3 font-mono text-xs font-bold {{ $isCancelled ? 'text-rose-700 line-through' : 'text-indigo-700' }}"><a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="hover:underline">{{ $package->report_document_number }}</a></td><td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isCancelled ? 'border border-rose-200 bg-rose-100 text-rose-800' : 'border border-emerald-200 bg-emerald-100 text-emerald-800' }}">{{ $isCancelled ? 'Dibatalkan' : 'Sukses' }}</span>@if($isCancelled && $package->report_cancellation_reason)<p class="mt-1 max-w-48 text-xs text-rose-700">{{ $package->report_cancellation_reason }}</p>@endif</td><td class="px-4 py-3"><p class="font-semibold">{{ $package->transaction->no_bukti }}</p><p class="text-xs text-slate-500">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td><td class="px-4 py-3">{{ $package->transaction->recipient_name }}</td><td class="px-4 py-3 text-right">{{ $rupiah($package->transaction->gross_amount) }}</td><td class="px-4 py-3 text-right {{ $isCancelled ? 'text-slate-400' : 'text-amber-700' }}">{{ $rupiah($package->transaction->tax_total) }}</td><td class="px-4 py-3 text-right font-bold {{ $isCancelled ? 'text-slate-400' : 'text-emerald-700' }}">{{ $rupiah($package->transaction->net_amount) }}</td></tr>@empty<tr><td colspan="7" class="px-5 py-14 text-center text-slate-500">Belum ada riwayat paket SPJ untuk filter ini.</td></tr>@endforelse</tbody></table></div>
            </div>

            {{-- Tab: Monitoring --}}
            <div x-show="tab === 'monitoring'" x-transition>
                <div class="border-b border-amber-100 bg-amber-50/40 px-5 py-4 sm:px-6">
                    <div><h2 class="font-bold text-amber-900">Monitoring Dokumen Belum Lengkap</h2><p class="mt-1 text-base text-amber-800">Transaksi ber-rincian tapi paket belum siap atau belum bernomor · <span class="font-bold">{{ $pendingPaginator?->total() ?? 0 }} transaksi</span></p></div>
                    @if(auth()->user()?->isAdministrator())
                        <form method="POST" action="{{ route('spj.quarter-numbering') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-indigo-200 bg-[var(--ui-surface-base)] p-3" data-confirm="Rekonsiliasi nomor triwulan ini? Transaksi yang sudah memiliki nomor aktif akan dilewati dan slot nomor yang dibatalkan dapat dipakai dokumen berikutnya dalam domain serta periode yang sama.">
                            @csrf
                            <x-ui.field label="Triwulan siap dinomori"><x-ui.select name="quarter">@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>@endforeach</x-ui.select></x-ui.field>
                            <x-ui.button type="submit">Tetapkan nomor triwulan</x-ui.button>
                            <p class="basis-full text-xs text-[var(--ui-fg-muted)]">Nomor aktif dipertahankan. Slot nomor batal dipakai kembali menurut urutan terkecil oleh dokumen berikutnya dalam jenis dan periode penomoran yang sama.</p>
                            <p class="basis-full text-xs text-[var(--ui-fg-muted)]">Setiap jenis dokumen diurutkan menurut tanggal peristiwanya. Nomor yang sudah terbit akan dilewati.</p>
                        </form>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach(range(1,4) as $quarter)
                                @php
                                    $period = ($periodClosures ?? collect())->get($quarter);
                                @endphp
                                <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3"><div class="flex items-center justify-between"><b>Triwulan {{ $quarter }}</b><span class="rounded-full bg-[var(--ui-surface-muted)] px-2 py-1 text-xs font-bold">{{ $period?->status ?? 'OPEN' }}</span></div>
                                    @if($period?->status === 'NUMBERED')<form method="POST" action="{{ route('spj.quarter-close') }}" class="mt-2">@csrf<input type="hidden" name="quarter" value="{{ $quarter }}"><x-ui.button type="submit" variant="secondary" class="w-full px-3 py-1.5 text-xs">Tutup triwulan</x-ui.button></form>@endif
                                    @if($period?->status === 'CLOSED')<form method="POST" action="{{ route('spj.quarter-reopen', $period->id) }}" class="mt-2 space-y-2">@csrf<x-ui.input name="reason" required placeholder="Alasan pembukaan" class="text-xs" /><x-ui.button type="submit" variant="warning" class="w-full px-3 py-1.5 text-xs">Buka kembali</x-ui.button></form>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="overflow-x-auto p-5"><table class="min-w-full divide-y divide-amber-100 text-base"><thead class="bg-amber-50"><tr><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">BUKTI</th><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">URAIAN</th><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">STATUS</th><th class="px-4 py-3 text-right text-xs font-bold text-amber-800">AKSI</th></tr></thead><tbody class="divide-y divide-amber-100">@forelse($pendingPaginator ?? [] as $transaction)@php
                    $wasCancelled = $transaction->spjPackage?->documents?->contains('status', 'CANCELLED') ?? false;
                @endphp<tr class="transition {{ $wasCancelled ? 'bg-rose-50/60 hover:bg-rose-50' : 'hover:bg-amber-50/60' }}"><td class="px-4 py-3 font-mono font-bold {{ $wasCancelled ? 'text-rose-800' : 'text-amber-900' }}">{{ $transaction->no_bukti }}</td><td class="px-4 py-3 max-w-sm truncate">{{ $transaction->description }}</td><td class="px-4 py-3"><span class="rounded-full border px-2 py-0.5 text-xs font-bold {{ $wasCancelled ? 'border-rose-200 bg-rose-100 text-rose-800' : ($transaction->spjPackage ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-[var(--ui-line)] bg-[var(--ui-surface-muted)] text-slate-500') }}">{{ $wasCancelled ? 'Dibatalkan — menunggu nomor baru' : ($transaction->spjPackage ? 'Draft — nomor belum ditetapkan' : 'Paket belum disiapkan') }}</span></td><td class="px-4 py-3 text-right">@if($transaction->spjPackage)<a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}" class="font-bold text-indigo-700 hover:underline">{{ $wasCancelled ? 'Periksa paket →' : 'Lengkapi paket →' }}</a>@else<a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="font-bold text-indigo-700 hover:underline">Buka persiapan →</a>@endif</td></tr>@empty<tr><td colspan="4" class="px-5 py-10 text-center text-emerald-700">Tidak ada transaksi ber-rincian yang tertunda.</td></tr>@endforelse</tbody></table></div>
            </div>
        </section>
    </div>

    <div id="template-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="template-preview-title">
        <div class="flex h-[min(92vh,1100px)] w-full max-w-[1600px] flex-col overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-2xl">
            <header class="flex shrink-0 items-center justify-between gap-3 border-b border-[var(--ui-line)] px-4 py-3">
                <div><p class="text-xs font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">Pratinjau Template</p><h2 id="template-preview-title" class="mt-0.5 font-bold" style="color: var(--ui-fg-strong)">Dokumen SPJ</h2></div>
                <button type="button" data-close-template-preview class="ui-btn ui-btn-secondary min-h-10 px-4">Tutup</button>
            </header>
            <div class="min-h-0 flex-1 overflow-auto bg-[var(--ui-surface-muted)] p-3"><iframe id="template-preview-frame" title="Pratinjau template SPJ" class="h-full min-h-[760px] w-full rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)]"></iframe></div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('template-preview-modal');
            const frame = document.getElementById('template-preview-frame');
            const title = document.getElementById('template-preview-title');
            const close = () => { modal?.classList.add('hidden'); modal?.classList.remove('flex'); if (frame) frame.src = 'about:blank'; };
            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-template-preview]');
                if (!button || button.closest('[inert]')) return;
                if (! frame || ! modal) return;
                title.textContent = button.dataset.templateName || 'Pratinjau Template';
                frame.src = button.dataset.templatePreview;
                modal.classList.remove('hidden'); modal.classList.add('flex');
            });
            document.querySelectorAll('[data-close-template-preview]').forEach((button) => button.addEventListener('click', close));
            modal?.addEventListener('click', (event) => { if (event.target === modal) close(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
        })();
    </script>
</x-layouts.tailwind-app>
