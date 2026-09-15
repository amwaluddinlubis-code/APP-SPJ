<x-layouts.tailwind-app>
    @php
        $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
            'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'BARANG' => 'Barang',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'SPPD' => 'SPPD',
            default => ucwords(strtolower(str_replace('_', ' ', (string) $value))),
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
            const url = new URL(window.location.href);
            url.searchParams.set('tab', name);
            if (name !== 'paket') url.searchParams.delete('package_id');
            if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                window.Livewire.navigate(url.toString());
                return;
            }
            this.loadingTab = true;
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

            {{-- Tab: Persiapan (filter AJAX via Livewire, tanpa reload; hanya tab aktif yang dirender server) --}}
            @if(($tab ?? 'persiapan') === 'persiapan')
            <div x-show="tab === 'persiapan'" x-transition>
                <livewire:spj-preparation-filter />
            </div>
            @endif
            {{-- Tab: Paket --}}
            @if(($tab ?? 'persiapan') === 'paket')
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
                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
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
                            <nav class="flex gap-1 overflow-x-auto border-b border-[var(--ui-line)] px-2 pt-1 text-base" role="tablist" aria-label="Bagian Paket SPJ" @click="const button = $event.target.closest('[data-package-tab]'); if (button) selectPackageTab(button.dataset.packageTab)" @keydown="if ($event.key === 'ArrowRight' || $event.key === 'ArrowLeft') { const buttons = [...$el.querySelectorAll('[data-package-tab]')]; const current = buttons.indexOf($event.target); const next = $event.key === 'ArrowRight' ? (current + 1) % buttons.length : (current - 1 + buttons.length) % buttons.length; buttons[next].focus(); selectPackageTab(buttons[next].dataset.packageTab); }">
                                <button type="button" role="tab" id="package-tab-rincian" aria-controls="package-panel-rincian" data-package-tab="rincian" :aria-selected="(packageTab === 'rincian').toString()" :data-active="packageTab === 'rincian'" class="spj-standard-tab"><x-ui.icon name="document" size="sm" /> <span>Rincian</span> <span class="ml-1 rounded-full bg-[var(--ui-surface-muted)] px-1.5 py-0.5 text-[11px]">{{ $transaction->items->count() }}</span></button>
                                <button type="button" role="tab" id="package-tab-isian" aria-controls="package-panel-isian" data-package-tab="isian" :aria-selected="(packageTab === 'isian').toString()" :data-active="packageTab === 'isian'" class="spj-standard-tab"><x-ui.icon name="edit" size="sm" /> <span>Isian Manual</span></button>
                                <button type="button" role="tab" id="package-tab-pajak" aria-controls="package-panel-pajak" data-package-tab="pajak" :aria-selected="(packageTab === 'pajak').toString()" :data-active="packageTab === 'pajak'" class="spj-standard-tab"><x-ui.icon name="tax" size="sm" /> <span>Rincian Pajak</span></button>
                                <button type="button" role="tab" id="package-tab-penomoran" aria-controls="package-panel-penomoran" data-package-tab="penomoran" :aria-selected="(packageTab === 'penomoran').toString()" :data-active="packageTab === 'penomoran'" class="spj-standard-tab"><x-ui.icon name="number" size="sm" /> <span>Penomoran</span> @if($hasActiveSpjNumber)<span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] text-emerald-700">OK</span>@elseif($package->status === 'CANCELLED')<span class="ml-1 rounded-full bg-rose-100 px-1.5 py-0.5 text-[11px] text-rose-700">Dibatalkan</span>@else<span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-700">Belum</span>@endif</button>
                            </nav>
                        </div>

                        <div x-show="packageTab === 'rincian'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-rincian" role="tabpanel" aria-labelledby="package-tab-rincian" data-panel="rincian" class="tab-panel">
                            @include('spj.partials.package.items-readonly')
                        </div>

                        <div x-show="packageTab === 'isian'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-isian" role="tabpanel" aria-labelledby="package-tab-isian" data-panel="isian" class="tab-panel" x-data="{saving:false}">
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

                        <div x-show="packageTab === 'pajak'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-pajak" role="tabpanel" aria-labelledby="package-tab-pajak" data-panel="pajak" class="tab-panel p-4">
                            @include('spj.partials.package.tax-reference')
                        </div>

                        <div x-show="packageTab === 'penomoran'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-penomoran" role="tabpanel" aria-labelledby="package-tab-penomoran" data-panel="penomoran" class="tab-panel p-4">
                            @include('spj.partials.package.numbering')
                        </div>
                    </section>
                @php
                    } else {
                @endphp
                    <livewire:spj-package-list />
                @php
                    }
                @endphp
            </div>
            @endif

            {{-- Tab: Laporan (filter AJAX via Livewire, tanpa reload) --}}
            @if(($tab ?? 'persiapan') === 'laporan')
            <div x-show="tab === 'laporan'" x-transition>
                <livewire:spj-report-filter />
            </div>
            @endif

            {{-- Tab: Monitoring --}}
            @if(($tab ?? 'persiapan') === 'monitoring')
            <div x-show="tab === 'monitoring'" x-transition>
                <div class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4 sm:px-6">
                    <div><h2 class="font-bold text-[var(--ui-fg-strong)]">Monitoring Dokumen Belum Lengkap</h2><p class="mt-1 text-base text-[var(--ui-fg-muted)]">Transaksi ber-rincian tapi paket belum siap atau belum bernomor · <span class="font-bold text-[var(--theme-content-accent)]">{{ $pendingPaginator?->total() ?? 0 }} transaksi</span></p></div>
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
                <livewire:spj-monitoring-list />
            </div>
            @endif
        </section>
    </div>

    @include('spj.partials.preview-modal')

    <script>
        (() => {
            // Delegasi penuh di document agar modal tetap berfungsi setelah
            // navigasi SPA mengganti isi body (tanpa listener pada elemen).
            const templatePreview = (action, button) => {
                const modal = document.getElementById('template-preview-modal');
                if (!modal) return;
                const frame = document.getElementById('template-preview-frame');
                const title = document.getElementById('template-preview-title');
                if (action === 'close') {
                    modal.classList.add('hidden'); modal.classList.remove('flex');
                    if (frame) frame.src = 'about:blank';
                    return;
                }
                if (!button || !frame) return;
                if (title) title.textContent = button.dataset.templateName || 'Pratinjau Template';
                frame.src = button.dataset.templatePreview;
                modal.classList.remove('hidden'); modal.classList.add('flex');
            };
            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-close-template-preview]')) {
                    templatePreview('close');
                    return;
                }
                const modal = document.getElementById('template-preview-modal');
                if (modal && !modal.classList.contains('hidden') && event.target === modal) {
                    templatePreview('close');
                    return;
                }
                const button = event.target.closest('[data-template-preview]');
                if (!button || button.closest('[inert]')) return;
                templatePreview('open', button);
            });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') templatePreview('close'); });
        })();
    </script>
</x-layouts.tailwind-app>
