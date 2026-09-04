<x-layouts.tailwind-app>
    @php
        $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $totalItems = $transaction->items->sum('amount');
        $descriptionsComplete = $transaction->items->isNotEmpty() && $transaction->items->every(fn ($item) => filled($item->item_description));
        $descriptionsFilled = $transaction->items->filter(fn ($item) => filled($item->item_description))->count();
        $spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'SPPD' => 'SPPD',
            'HONOR_PEGAWAI' => 'Honor Pegawai',
            default => str_replace('_', ' ', (string) $value),
        };
        $spjGuidance = [
            'BARANG' => ['title' => 'Barang', 'description' => 'Lengkapi uraian belanja barang serta data invoice atau pesanan pembelian.'],
            'KONSUMSI' => ['title' => 'Konsumsi', 'description' => 'Lengkapi uraian belanja makanan/minuman (katering) serta data invoice atau pesanan pembelian.'],
            'PEMELIHARAAN' => ['title' => 'Pemeliharaan', 'description' => 'Lengkapi uraian pekerjaan, lokasi, periode, SPK, dan penandatangan.'],
            'JASA_LAINNYA' => ['title' => 'Jasa Lainnya', 'description' => 'Lengkapi uraian jasa, referensi pembayaran, serta penerima atau penandatangan.'],
            'SPPD' => ['title' => 'SPPD', 'description' => 'Lengkapi tujuan perjalanan, lokasi, periode, dan referensi pembayaran.'],
            'HONOR_PEGAWAI' => ['title' => 'Honor Pegawai', 'description' => 'Lengkapi uraian honor, periode pembayaran, dan nama penerima honor.'],
        ];
        $selectedSpjType = strtoupper((string) ($transaction->spj_category ?: $transaction->spj_category));
        $selectedSpjType = match ($selectedSpjType) {
            'BELANJA_MODAL' => 'BARANG',
            'PERJALANAN_DINAS' => 'SPPD',
            'HONOR_PEGAWAI' => 'HONOR_PEGAWAI',
            'LAINNYA' => 'JASA_LAINNYA',
            'UPAH' => 'PEMELIHARAAN',
            default => $selectedSpjType,
        };
        $participantRows = $transaction->participants->map(fn ($participant) => [
            'name' => $participant->name,
            'position' => $participant->position,
            'portions' => (int) $participant->portions,
        ])->values()->all();
        $dapodikParticipantRows = $dapodikTeachers->map(fn ($employee) => ['employee_id' => $employee->id, 'name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type, 'portions' => 1])->values()->all();
        $employeeOptions = $dapodikEmployees->map(fn ($employee) => ['id' => $employee->id, 'name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type])->values()->all();
        if ($selectedSpjType === 'KONSUMSI') {
            $existingParticipantNames = collect($participantRows)
                ->map(fn ($row) => mb_strtolower(trim($row['name'])))
                ->all();
            $missingDapodikParticipants = collect($dapodikParticipantRows)
                ->reject(fn ($row) => in_array(mb_strtolower(trim($row['name'])), $existingParticipantNames, true));
            $participantRows = collect($participantRows)->concat($missingDapodikParticipants)->values()->all();
        }
        $participantRows = old('participants', $participantRows);
        $purchaseDetails = $transaction->goods->first();
        $workDetails = $transaction->workOrder;
        $transactionDateLimit = $transaction->transaction_date?->format('Y-m-d');
        $effectiveTaxRate = fn ($rate, $amount) => $rate !== null
            ? (float) $rate
            : ((float) $transaction->gross_amount > 0 ? (float) $amount / (float) $transaction->gross_amount * 100 : 0);
        $purchaseOrderDate = $purchaseDetails?->order_date?->format('Y-m-d') ?: $transaction->order_date?->format('Y-m-d') ?: $transactionDateLimit;
        $purchaseBapDate = $purchaseDetails?->bap_date?->format('Y-m-d') ?: $transaction->bap_date?->format('Y-m-d') ?: $transactionDateLimit;
        $purchaseBastDate = $purchaseDetails?->bast_date?->format('Y-m-d') ?: $transaction->bast_date?->format('Y-m-d') ?: $transactionDateLimit;
        $purchaseInvoiceDate = $transaction->invoice_date?->format('Y-m-d') ?: $transactionDateLimit;
        $workerRows = $transaction->workers->map(fn ($worker) => [
            'name' => $worker->name,
            'job_description' => $worker->job_description,
            'work_days' => $worker->work_days,
            'daily_rate' => $worker->daily_rate,
            'is_receipt_recipient' => (bool) $worker->is_receipt_recipient,
            'notes' => $worker->notes,
        ])->values()->all();
        $workerRows = $workerRows ?: [['name' => '', 'job_description' => '', 'work_days' => 1, 'daily_rate' => 0, 'is_receipt_recipient' => false, 'notes' => '']];
        $honorRows = $transaction->honors->map(fn ($honor) => [
            'name' => $honor->name,
            'job_description' => $honor->position,
            'work_days' => $honor->honor_months,
            'daily_rate' => $honor->rate_per_unit,
            'is_receipt_recipient' => false,
            'notes' => '',
        ])->values()->all();
        $honorRows = old('workers', $honorRows ?: [['name' => '', 'job_description' => '', 'work_days' => 1, 'daily_rate' => 0, 'is_receipt_recipient' => false, 'notes' => '']]);
        $travelRows = $transaction->travels->map(fn ($travel) => [
            'traveler_name' => $travel->traveler_name,
            'destination' => $travel->destination,
            'purpose' => $travel->purpose,
            'departure_date' => optional($travel->departure_date)->format('Y-m-d') ?: $transactionDateLimit,
            'assignment_letter_number' => $travel->assignment_letter_number,
            'assignment_letter_date' => optional($travel->assignment_letter_date)->format('Y-m-d') ?: $transactionDateLimit,
            'return_date' => optional($travel->return_date)->format('Y-m-d') ?: $transactionDateLimit,
            'transport_mode' => $travel->transport_mode,
            'amount' => $travel->amount,
            'notes' => $travel->notes,
        ])->values()->all();
        $travelRows = $travelRows ?: [['traveler_name' => '', 'destination' => '', 'purpose' => '', 'assignment_letter_number' => '', 'assignment_letter_date' => $transactionDateLimit, 'departure_date' => $transactionDateLimit, 'return_date' => $transactionDateLimit, 'transport_mode' => '', 'amount' => 0, 'notes' => '']];
        $category = strtoupper((string) $selectedSpjType);
        $manualChecklist = [
            ['label' => 'Kategori SPJ', 'ready' => filled($selectedSpjType), 'hint' => 'Pilih kategori agar form sesuai skenario dokumen.'],
            ['label' => 'Uraian dokumen', 'ready' => filled($transaction->payment_description), 'hint' => 'Isi uraian pembayaran yang akan masuk dokumen SPJ.'],
            ['label' => 'Penerima kuitansi', 'ready' => filled($transaction->effective_receipt_recipient_name), 'hint' => 'Boleh berbeda dari penerima BKU/ARKAS.'],
            ['label' => 'Metode pembayaran', 'ready' => filled($transaction->payment_method), 'hint' => 'Pilih Transfer Bank, SiPLah, atau Tunai.'],
            ['label' => 'Uraian item SPJ', 'ready' => $descriptionsComplete, 'hint' => "{$descriptionsFilled} dari {$transaction->items->count()} item sudah lengkap."],
        ];
        $categoryChecklist = match ($category) {
            'BARANG' => [
                ['label' => 'Data pembelian', 'ready' => filled($transaction->invoice_number) || filled($purchaseDetails?->order_number) || filled($transaction->order_number), 'hint' => 'Isi invoice atau nomor pesanan.'],
                ['label' => 'Dokumen penerimaan', 'ready' => filled($purchaseDetails?->bap_number) || filled($transaction->bap_number) || filled($purchaseDetails?->bast_number) || filled($transaction->bast_number), 'hint' => 'Isi BAP atau BAST jika sudah tersedia.'],
            ],
            'KONSUMSI' => [
                ['label' => 'Data acara', 'ready' => filled($transaction->event_name) || filled($transaction->event_location), 'hint' => 'Isi nama acara dan tempat.'],
                ['label' => 'Peserta/porsi', 'ready' => $transaction->participants->isNotEmpty(), 'hint' => 'Tambahkan minimal satu peserta atau dasar porsi.'],
            ],
            'PEMELIHARAAN' => [
                ['label' => 'Work order', 'ready' => filled($workDetails?->work_description), 'hint' => 'Isi deskripsi pekerjaan pemeliharaan.'],
                ['label' => 'Daftar pekerja', 'ready' => $transaction->workers->isNotEmpty(), 'hint' => 'Tambahkan pekerja dalam tabel.'],
                ['label' => 'Penerima kuitansi pekerja', 'ready' => filled($transaction->receipt_recipient_name) || $transaction->workers->contains(fn ($worker) => (bool) $worker->is_receipt_recipient), 'hint' => 'Tandai salah satu pekerja atau isi penerima kuitansi manual.'],
            ],
            'SPPD' => [
                ['label' => 'Pelaksana perjalanan', 'ready' => $transaction->travels->isNotEmpty(), 'hint' => 'Satu pembayaran boleh berisi banyak pelaksana.'],
                ['label' => 'Tanggal perjalanan', 'ready' => $transaction->travels->contains(fn ($travel) => filled($travel->departure_date)), 'hint' => 'Isi minimal tanggal berangkat.'],
            ],
            'HONOR_PEGAWAI' => [
                ['label' => 'Penerima honor', 'ready' => $transaction->honors->isNotEmpty(), 'hint' => 'Tambahkan minimal satu penerima honor.'],
            ],
            'JASA_LAINNYA' => [
                ['label' => 'Uraian jasa', 'ready' => filled($transaction->work_description) || filled($transaction->payment_description), 'hint' => 'Isi uraian jasa atau pembayaran.'],
            ],
            default => [],
        };
        $readinessChecklist = array_merge($manualChecklist, $categoryChecklist);
        $readyCount = collect($readinessChecklist)->where('ready', true)->count();
        $sourceStatus = strtoupper((string) ($transaction->source_status ?: 'ACTIVE'));
        $needsAttention = $sourceStatus === 'SOURCE_MISSING' || (bool) $transaction->requires_reconciliation;
    @endphp
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('transactions.index') }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-base font-semibold text-slate-700 shadow transition hover:bg-slate-50">← Kembali ke transaksi</a>
        <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $transaction->status === 'DITETAPKAN' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $transaction->status }}</span><span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $sourceStatus === 'SOURCE_MISSING' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $sourceStatus === 'SOURCE_MISSING' ? 'Tidak muncul di sync terakhir' : 'Data ARKAS aktif' }}</span>@if($transaction->requires_reconciliation)<span class="rounded-full bg-orange-50 px-3 py-1.5 text-xs font-bold text-orange-700">Perlu rekonsiliasi</span>@endif @if($transaction->spj_category)<span class="rounded-full bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700">SPJ: {{ $spjTypeLabel($transaction->spj_category) }}</span>@endif @if($transaction->spjPackage?->document_number)<span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">{{ $transaction->spjPackage->document_number }}</span>@elseif($transaction->items->isNotEmpty())<a href="#modul-buat-spj" class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white shadow hover:bg-violet-700">Buat SPJ</a>@endif</div>
        </div>

        @if($needsAttention)
            <div class="rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900 shadow-sm">
                <p class="font-bold">Transaksi ini perlu perhatian sebelum dokumen difinalkan.</p>
                <p class="mt-1">
                    @if($sourceStatus === 'SOURCE_MISSING')
                        Data ARKAS transaksi ini tidak muncul pada sinkronisasi terakhir. Data manual tetap dipertahankan.
                    @endif
                    @if($transaction->requires_reconciliation)
                        Ada perubahan sumber ARKAS yang perlu ditinjau agar dokumen tidak berubah diam-diam.
                    @endif
                </p>
            </div>
        @endif

        <x-page-header
            :title="$transaction->no_bukti"
            :subtitle="$transaction->payment_description ?: $transaction->description ?: 'Uraian transaksi belum tersedia.'"
            kicker="{{ $headerVisual['label'] }} · Detail transaksi / paket SPJ"
        >
            <div class="grid sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-item label="Nilai bruto" :value="$rupiah($transaction->gross_amount)" :hint="$transaction->transaction_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia'" />
                <x-stat-item label="Total pajak" :value="$rupiah($transaction->tax_total)" hint="PPN, PPh, dan pajak daerah" />
                <x-stat-item label="Nilai dibayarkan" :value="$rupiah($transaction->net_amount)" :hint="['transfer_bank' => 'Transfer Bank', 'siplah' => 'SiPLah', 'tunai' => 'Tunai'][$paymentMethod] ?? 'Cara bayar belum diisi'" />
                <x-stat-item label="Rincian barang/jasa" value="{{ $transaction->items->count() }} item" :hint="'Akumulasi: '.$rupiah($totalItems)" />
            </div>
        </x-page-header>

        <section>
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow xl:order-2">
                <div class="border-b border-slate-100 px-5 py-3.5"><h2 class="font-bold text-slate-800">Informasi Referensi</h2></div>
                <div class="grid divide-y divide-slate-100 md:grid-cols-3 md:divide-x md:divide-y-0">
                    <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Penerima / Penyedia</p><p class="mt-1 font-semibold text-slate-800">{{ $transaction->recipient_name ?: 'Belum diisi' }}</p></div>
                    <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Kode Kegiatan</p><p class="mt-1 font-mono text-base font-semibold text-indigo-700">{{ $transaction->activity_code ?: '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ $transaction->activity_name ?: 'Kegiatan belum tersedia' }}</p></div>
                    <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Kode Rekening</p><p class="mt-1 font-mono text-base font-semibold text-sky-700">{{ $transaction->account_code ?: '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ $transaction->account_name ?: 'Rekening belum tersedia' }}</p></div>
                </div>
            </article>
        </section>

        <section class="grid gap-4 lg:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)]">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Alur operator</p>
                        <h2 class="mt-1 font-bold text-slate-800">Status pekerjaan transaksi</h2>
                    </div>
                    <span class="rounded-full {{ $readyCount === count($readinessChecklist) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-xs font-bold">{{ $readyCount }}/{{ count($readinessChecklist) }} siap</span>
                </div>
                <ol class="mt-4 space-y-2 text-sm">
                    <li class="flex gap-2"><span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">1</span><span><strong>Pilih konteks</strong><br><span class="text-xs text-slate-500">Sekolah, tahun, dan sumber dana aktif.</span></span></li>
                    <li class="flex gap-2"><span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">2</span><span><strong>Cek data ARKAS</strong><br><span class="text-xs text-slate-500">{{ $sourceStatus === 'SOURCE_MISSING' ? 'Tidak muncul di sync terakhir.' : 'Data sumber aktif.' }}</span></span></li>
                    <li class="flex gap-2"><span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full {{ filled($selectedSpjType) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }} text-xs font-bold">3</span><span><strong>Lengkapi data manual</strong><br><span class="text-xs text-slate-500">Kategori, uraian, penerima kuitansi, dan detail sesuai SPJ.</span></span></li>
                    <li class="flex gap-2"><span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full {{ $transaction->spjPackage ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }} text-xs font-bold">4</span><span><strong>Buat paket SPJ</strong><br><span class="text-xs text-slate-500">{{ $transaction->spjPackage ? 'Paket sudah dibuat.' : 'Belum dibuat.' }}</span></span></li>
                    <li class="flex gap-2"><span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full {{ $transaction->spjPackage?->document_number ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }} text-xs font-bold">5</span><span><strong>Nomor & arsip</strong><br><span class="text-xs text-slate-500">{{ $transaction->spjPackage?->document_number ?: 'Belum bernomor.' }}</span></span></li>
                </ol>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Checklist kelengkapan</p>
                        <h2 class="mt-1 font-bold text-slate-800">Yang perlu dilengkapi operator</h2>
                    </div>
                    <a href="#modul-buat-spj" class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white shadow hover:bg-violet-700">Isi data</a>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-2">
                    @foreach($readinessChecklist as $item)
                        <div class="rounded-lg border {{ $item['ready'] ? 'border-emerald-100 bg-emerald-50' : 'border-amber-100 bg-amber-50' }} p-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full {{ $item['ready'] ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }} text-[11px] font-bold">{{ $item['ready'] ? '✓' : '!' }}</span>
                                <p class="text-sm font-bold {{ $item['ready'] ? 'text-emerald-800' : 'text-amber-800' }}">{{ $item['label'] }}</p>
                            </div>
                            <p class="mt-1 pl-7 text-xs {{ $item['ready'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $item['ready'] ? 'Sudah tersedia.' : $item['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section id="modul-buat-spj" class="spj-builder order-2 overflow-hidden rounded-2xl border bg-white shadow-sm" x-data="{ category: '{{ $selectedSpjType }}' }">
            <div class="spj-builder-header border-b px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="inline-flex rounded-full bg-white/15 px-3 py-1 text-sm font-bold text-sky-100 ring-1 ring-inset ring-white/20">MODUL PEMBUATAN SPJ</p>
                        <h2 class="mt-1 font-mono font-bold uppercase text-lg text-shadow-lg text-slate-300">Siapkan dokumen berdasarkan kategori SPJ</h2>
                        <p class="mt-1 text-sm text-slate-100">Pilih skenario dokumen, pastikan uraian setiap item lengkap, lalu buat paket SPJ.</p>
                    </div>
                    @if($transaction->spjPackage)
                        <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}" class="spj-builder-primary inline-flex w-fit rounded-lg px-4 py-2 text-base font-bold text-white shadow-sm">Buka Paket SPJ →</a>
                    @endif
                </div>

            </div>

            <div class="p-4 sm:p-5">
                <form method="POST" action="{{ route('spj.prepare', $transaction->id) }}" class="spj-builder-form rounded-xl border bg-white p-3 sm:p-4">
                    @csrf
                    <input type="hidden" name="ppn_rate" value="{{ $effectiveTaxRate($transaction->ppn_rate, $transaction->ppn) }}">
                    <input type="hidden" name="pph21_rate" value="{{ $effectiveTaxRate($transaction->pph21_rate, $transaction->pph21) }}">
                    <input type="hidden" name="pph22_rate" value="{{ $effectiveTaxRate($transaction->pph22_rate, $transaction->pph22) }}">
                    <input type="hidden" name="pph23_rate" value="{{ $effectiveTaxRate($transaction->pph23_rate, $transaction->pph23) }}">
                    <input type="hidden" name="pph4_rate" value="{{ $effectiveTaxRate($transaction->pph4_rate, $transaction->pph4) }}">
                    <input type="hidden" name="sspd_rate" value="{{ $effectiveTaxRate($transaction->sspd_rate, $transaction->sspd) }}">
                    @if($transaction->spjPackage && !$transaction->spjPackage->isEditable())
                        <div class="mb-3 flex items-start gap-2 rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-700"><span aria-hidden="true">🔒</span><p><strong>Paket terkunci.</strong> Batalkan penomoran lalu buka paket untuk koreksi sebelum mengubah isian.</p></div>
                    @endif
                    <fieldset @disabled($transaction->spjPackage && !$transaction->spjPackage->isEditable()) class="disabled:cursor-not-allowed disabled:opacity-60">
                    <div class="mb-3 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800"><span class="font-black">*</span><p><strong>Wajib diisi.</strong> Penanda menyesuaikan kategori SPJ yang dipilih; field tanpa tanda bintang bersifat opsional atau terisi otomatis.</p></div>
                    <div class="spj-builder-accent-panel grid gap-3 rounded-lg border p-3 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                        <div>
                            <label for="detail-spj-type" class="text-[11px] font-bold uppercase tracking-wide text-slate-700">Kategori SPJ <span class="text-rose-600">* Wajib diisi</span></label>
                            <select id="detail-spj-type" name="spj_category" x-model="category" class="mt-1 w-full rounded-md border border-violet-200 bg-white px-2.5 py-2 text-sm focus:border-violet-500 focus:ring-violet-500">
                                <option value="">Pilih kategori SPJ</option>
                                @foreach(array_keys($spjGuidance) as $type)
                                    <option value="{{ $type }}">{{ $spjGuidance[$type]['title'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rounded-md border border-sky-100 bg-white/70 p-2.5">
                            @foreach($spjGuidance as $type => $guidance)
                                <div x-show="category === '{{ $type }}'" class="text-xs text-sky-900">
                                    <p class="font-bold">{{ $guidance['title'] }}</p>
                                    <p class="mt-0.5 leading-relaxed">{{ $guidance['description'] }}</p>
                                </div>
                            @endforeach
                            <p x-show="!category" class="text-xs text-sky-800">Pilih kategori untuk menampilkan isian manual yang sesuai.</p>
                        </div>
                    </div>

                    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Isian umum semua kategori</p>
                        <div class="mt-2 grid gap-2 lg:grid-cols-4">
                            <div class="lg:col-span-2"><label class="text-[11px] font-semibold text-slate-700">Uraian dari ARKAS</label><textarea name="description" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm">{{ $transaction->description }}</textarea></div>
                            <div class="lg:col-span-2"><label class="text-[11px] font-semibold text-slate-700">Uraian dokumen / pembayaran <span class="text-rose-600">*</span></label><textarea name="payment_description" rows="2" required class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" placeholder="Uraian yang akan dipakai pada dokumen SPJ">{{ $transaction->payment_description }}</textarea></div>
                            <div><label class="text-[11px] font-semibold text-slate-700">Metode Pembayaran <span class="text-rose-600">*</span></label><select name="payment_method" required class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-sm"><option value="transfer_bank" @selected($paymentMethod === 'transfer_bank')>Transfer Bank (CMS / Non Tunai)</option><option value="siplah" @selected($paymentMethod === 'siplah')>SiPLah Kemdikbud</option><option value="tunai" @selected($paymentMethod === 'tunai')>Tunai Kas BOS</option></select></div>
                            <div><label class="text-[11px] font-semibold text-slate-700">Referensi Pembayaran</label><input name="payment_reference" value="{{ $transaction->payment_reference }}" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" placeholder="No. cek/CMS/kuitansi"></div>
                            <div><label class="text-[11px] font-semibold text-slate-700">Penerima Kuitansi <span class="text-rose-600">*</span></label><input name="receipt_recipient_name" required value="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" placeholder="Boleh berbeda dari penerima BKU"></div>
                            <div><label class="text-[11px] font-semibold text-slate-700">Nama Penandatangan</label><input name="signatory_name" value="{{ $transaction->signatory_name }}" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" placeholder="Nama penerima/penanggung jawab"></div>
                            <div><label class="text-[11px] font-semibold text-slate-700">Jabatan/Peran</label><input name="signatory_role" value="{{ $transaction->signatory_role }}" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" placeholder="Bendahara, penerima, dll"></div>
                        </div>
                    </div>

                    <div x-show="['BARANG','KONSUMSI'].includes(category)" x-cloak x-data="{ orderDate: @js($purchaseOrderDate), bapDate: @js($purchaseBapDate), bastDate: @js($purchaseBastDate), invoiceDate: @js($purchaseInvoiceDate) }" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-sky-900">Data pembelian barang/konsumsi</p>
                        <div class="mt-2 grid gap-2 md:grid-cols-3 xl:grid-cols-4">
                            <div><label class="text-[11px] font-semibold text-sky-900">No. Invoice/Faktur</label><input name="invoice_number" value="{{ $transaction->invoice_number }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm" placeholder="No. invoice"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tgl Invoice</label><input type="date" name="invoice_date" x-model="invoiceDate" :min="bastDate || null" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">No. Pesanan <span class="text-emerald-700">(otomatis)</span></label><input readonly name="order_number" value="{{ $purchaseDetails?->order_number ?: $transaction->order_number }}" class="mt-1 w-full rounded-md border border-sky-200 bg-slate-100 px-2.5 py-1.5 text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tgl Pesanan</label><input type="date" name="order_date" x-model="orderDate" :max="bapDate || @js($transactionDateLimit)" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">No. BAP <span class="text-emerald-700">(otomatis)</span></label><input readonly name="bap_number" value="{{ $purchaseDetails?->bap_number ?: $transaction->bap_number }}" class="mt-1 w-full rounded-md border border-sky-200 bg-slate-100 px-2.5 py-1.5 text-sm" placeholder="Terbit setelah penomoran"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tgl BAP</label><input type="date" name="bap_date" x-model="bapDate" :min="orderDate || null" :max="bastDate || @js($transactionDateLimit)" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">No. BAST <span class="text-emerald-700">(otomatis)</span></label><input readonly name="bast_number" value="{{ $purchaseDetails?->bast_number ?: $transaction->bast_number }}" class="mt-1 w-full rounded-md border border-sky-200 bg-slate-100 px-2.5 py-1.5 text-sm" placeholder="Terbit setelah penomoran"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tgl BAST</label><input type="date" name="bast_date" x-model="bastDate" :min="bapDate || null" :max="invoiceDate || @js($transactionDateLimit)" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                        </div>
                    </div>

                    <fieldset x-show="category === 'KONSUMSI'" :disabled="category !== 'KONSUMSI'" x-cloak x-data="{ rows: @js($participantRows), dapodikRows: @js($dapodikParticipantRows), participantCount: {{ (int) old('participant_count', $transaction->participant_count ?: collect($participantRows)->sum('portions')) }}, dragIndex: null, get portionTotal() { return this.rows.reduce((total, row) => total + (parseInt(row.portions) || 0), 0); }, fillTeachers() { this.rows = this.dapodikRows.map(row => ({...row})); this.participantCount = this.portionTotal; }, move(index, direction) { const target = index + direction; if (target < 0 || target >= this.rows.length) return; [this.rows[index], this.rows[target]] = [this.rows[target], this.rows[index]]; this.rows = [...this.rows]; }, dropAt(index) { if (this.dragIndex === null || this.dragIndex === index) return; const [row] = this.rows.splice(this.dragIndex, 1); this.rows.splice(index, 0, row); this.rows = [...this.rows]; this.dragIndex = null; } }" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><p class="text-[11px] font-bold uppercase tracking-wide text-sky-900">Data acara & peserta konsumsi</p><p class="mt-0.5 text-xs text-sky-700">Peserta digunakan sebagai dasar porsi konsumsi.</p></div>
                            <div class="flex flex-wrap gap-2"><button type="button" @click="fillTeachers()" class="rounded-md border border-sky-300 bg-white px-2.5 py-1.5 text-xs font-bold text-sky-800 hover:bg-sky-100">Ambil semua pegawai terdaftar</button><button type="button" @click="rows.push({name:'', position:'', portions:1})" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-sky-700">+ Peserta manual</button></div>
                        </div>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div><label class="text-[11px] font-semibold text-sky-900">Nama Acara/Rapat <span class="text-rose-600">*</span></label><input required name="event_name" value="{{ old('event_name', $transaction->event_name) }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm" placeholder="Nama acara/rapat"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tempat Pelaksanaan <span class="text-rose-600">*</span></label><input required name="event_location" value="{{ old('event_location', $transaction->event_location) }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm" placeholder="Tempat"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tanggal Kegiatan <span class="text-rose-600">*</span></label><input required type="date" name="event_date" value="{{ old('event_date', $transaction->event_date?->format('Y-m-d') ?: $transactionDateLimit) }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Jumlah Peserta <span class="text-rose-600">*</span></label><input required type="number" min="1" step="1" name="participant_count" x-model.number="participantCount" class="mt-1 w-full rounded-md border px-2.5 py-1.5 text-sm" :class="participantCount === portionTotal ? 'border-sky-200 bg-white' : 'border-rose-400 bg-rose-50'"></div>
                        </div>
                        <p x-show="participantCount !== portionTotal" class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" x-text="'Jumlah peserta harus sama dengan total porsi (' + portionTotal + ').'"></p>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wide text-sky-800"><th class="px-2 py-1.5">No</th><th class="px-2 py-1.5">Nama peserta</th><th class="px-2 py-1.5">Jabatan/Instansi</th><th class="px-2 py-1.5">Porsi</th><th class="px-2 py-1.5">Aksi</th></tr></thead>
                                <tbody>
                                    <template x-for="(row, index) in rows" :key="index">
                                        <tr @dragover.prevent @drop.prevent="dropAt(index)" :class="dragIndex === index ? 'bg-sky-100' : ''">
                                            <td class="px-2 py-1.5 font-semibold text-sky-700"><div class="flex items-center gap-2"><button type="button" draggable="true" @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'" @dragend="dragIndex = null" title="Seret untuk mengubah urutan" class="cursor-grab rounded border border-sky-200 bg-white px-1.5 py-1 text-slate-500 active:cursor-grabbing">⋮⋮</button><span x-text="index + 1"></span></div></td>
                                            <td class="px-2 py-1.5"><input :name="`participants[${index}][name]`" x-model="row.name" aria-label="Nama peserta" class="w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Nama lengkap"></td>
                                            <td class="px-2 py-1.5"><input :name="`participants[${index}][position]`" x-model="row.position" aria-label="Jabatan atau instansi" class="w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Jabatan/instansi"></td>
                                            <td class="px-2 py-1.5"><input required type="number" min="1" step="1" :name="`participants[${index}][portions]`" x-model.number="row.portions" aria-label="Jumlah porsi" class="w-20 rounded-md border border-sky-200 px-2 py-1.5 text-right text-sm"></td>
                                            <td class="px-2 py-1.5"><div class="flex items-center gap-1"><button type="button" @click="move(index, -1)" :disabled="index === 0" title="Naikkan urutan" class="rounded-md border border-sky-200 px-2 py-1.5 text-xs font-bold text-sky-700 disabled:cursor-not-allowed disabled:opacity-35">↑</button><button type="button" @click="move(index, 1)" :disabled="index === rows.length - 1" title="Turunkan urutan" class="rounded-md border border-sky-200 px-2 py-1.5 text-xs font-bold text-sky-700 disabled:cursor-not-allowed disabled:opacity-35">↓</button><button type="button" @click="rows.splice(index, 1)" class="rounded-md border border-rose-200 px-2 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">Hapus</button></div></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>

                    <fieldset x-show="category === 'PEMELIHARAAN'" :disabled="category !== 'PEMELIHARAAN'" x-cloak x-data="{ rows: @js($workerRows), dragIndex: null, move(index, direction) { const target = index + direction; if (target < 0 || target >= this.rows.length) return; [this.rows[index], this.rows[target]] = [this.rows[target], this.rows[index]]; this.rows = [...this.rows]; }, dropAt(index) { if (this.dragIndex === null || this.dragIndex === index) return; const [row] = this.rows.splice(this.dragIndex, 1); this.rows.splice(index, 0, row); this.rows = [...this.rows]; this.dragIndex = null; } }" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><p class="text-[11px] font-bold uppercase tracking-wide text-sky-900">Work order pemeliharaan</p><p class="mt-0.5 text-xs text-sky-700">1 transaksi pemeliharaan → 1 work order → banyak pekerja.</p></div>
                            <button type="button" @click="rows.push({name:'', job_description:'', work_days:1, daily_rate:0, is_receipt_recipient:false, notes:''})" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-sky-700">+ Pekerja</button>
                        </div>
                        <div class="mt-2 grid gap-2 lg:grid-cols-4">
                            <div class="lg:col-span-4">
                                <label class="text-[11px] font-semibold text-sky-900">Deskripsi Pekerjaan</label>
                                <textarea name="work_description" rows="2" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm" placeholder="Uraian pekerjaan">{{ $workDetails?->work_description }}</textarea>
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-sky-900">Lokasi</label>
                                <input name="work_location" value="{{ $workDetails?->work_location ?: $transaction->work_location }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm" placeholder="Lokasi pekerjaan">
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-sky-900">No. SPK <span class="text-emerald-700">(otomatis)</span></label>
                                <input readonly name="spk_number" value="{{ $workDetails?->spk_number ?: $transaction->spk_number }}" class="mt-1 w-full rounded-md border border-sky-200 bg-slate-100 px-2.5 py-1.5 text-sm" placeholder="Terbit setelah penomoran">
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-sky-900">Tgl SPK</label>
                                <input type="date" name="spk_date" value="{{ $workDetails?->spk_date?->format('Y-m-d') ?: $transaction->spk_date?->format('Y-m-d') ?: $transactionDateLimit }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm">
                            </div>
                            <div><label class="text-[11px] font-semibold text-sky-900">No. RAB <span class="text-emerald-700">(otomatis)</span></label><input readonly name="rab_number" value="{{ $workDetails?->rab_number }}" class="mt-1 w-full rounded-md border border-sky-200 bg-slate-100 px-2.5 py-1.5 text-sm" placeholder="Terbit setelah penomoran"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tgl RAB</label><input type="date" name="rab_date" value="{{ $workDetails?->rab_date?->format('Y-m-d') ?: $transactionDateLimit }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                            <div>
                                <label class="text-[11px] font-semibold text-sky-900">Tgl Mulai</label>
                                <input type="date" name="work_started_at" value="{{ $transaction->work_started_at?->format('Y-m-d') ?: $transactionDateLimit }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm">
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-sky-900">Tgl Selesai</label>
                                <input type="date" name="work_completed_at" value="{{ $transaction->work_completed_at?->format('Y-m-d') ?: $transactionDateLimit }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm">
                            </div>
                        </div>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-sky-800">
                                        <th class="w-10 px-2 py-1.5">No</th>
                                        <th class="min-w-44 px-2 py-1.5">Nama Pekerja</th>
                                        <th class="min-w-52 px-2 py-1.5">Uraian Pekerjaan</th>
                                        <th class="w-24 px-2 py-1.5 text-right">Hari</th>
                                        <th class="w-32 px-2 py-1.5 text-right">Tarif</th>
                                        <th class="w-36 px-2 py-1.5 text-center">Penerima</th>
                                        <th class="min-w-44 px-2 py-1.5">Catatan</th>
                                        <th class="w-20 px-2 py-1.5"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in rows" :key="index">
                                        <tr @dragover.prevent @drop.prevent="dropAt(index)" :class="dragIndex === index ? 'bg-sky-100' : ''">
                                            <td class="px-2 py-1.5 font-semibold text-sky-700"><div class="flex items-center gap-2"><button type="button" draggable="true" @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'" @dragend="dragIndex = null" title="Seret untuk mengubah urutan" class="cursor-grab rounded border border-sky-200 bg-white px-1.5 py-1 text-slate-500 active:cursor-grabbing">⋮⋮</button><span x-text="index + 1"></span></div></td>
                                            <td class="px-2 py-1.5"><label class="block"><span class="text-[11px] font-semibold text-sky-900">Nama Pekerja</span><input :name="`workers[${index}][name]`" x-model="row.name" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Nama pekerja"></label></td>
                                            <td class="px-2 py-1.5"><label class="block"><span class="text-[11px] font-semibold text-sky-900">Uraian Pekerjaan</span><input :name="`workers[${index}][job_description]`" x-model="row.job_description" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Jenis pekerjaan"></label></td>
                                            <td class="px-2 py-1.5"><label class="block"><span class="text-[11px] font-semibold text-sky-900">Hari</span><input type="number" min="0" step=".5" :name="`workers[${index}][work_days]`" x-model="row.work_days" class="mt-1 w-20 rounded-md border border-sky-200 px-2 py-1.5 text-right text-sm" placeholder="Hari"></label></td>
                                            <td class="px-2 py-1.5"><label class="block"><span class="text-[11px] font-semibold text-sky-900">Tarif</span><input type="hidden" :name="`workers[${index}][daily_rate]`" :value="row.daily_rate"><input type="text" inputmode="numeric" :value="new Intl.NumberFormat('en-US').format(Number(row.daily_rate) || 0)" @input="row.daily_rate = Number($event.target.value.replace(/[^0-9]/g, '')); $event.target.value = new Intl.NumberFormat('en-US').format(row.daily_rate)" class="mt-1 w-28 rounded-md border border-sky-200 px-2 py-1.5 text-right text-sm" placeholder="0"></label></td>
                                            <td class="px-2 py-1.5 text-center"><label class="inline-flex flex-col items-center gap-1 text-[11px] font-semibold text-sky-900"><span>Penerima</span><span class="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-white px-2 py-1.5"><input type="hidden" :name="`workers[${index}][is_receipt_recipient]`" value="0"><input type="checkbox" :name="`workers[${index}][is_receipt_recipient]`" value="1" x-model="row.is_receipt_recipient" class="rounded border-sky-300 text-sky-600"> Ya</span></label></td>
                                            <td class="px-2 py-1.5"><label class="block"><span class="text-[11px] font-semibold text-sky-900">Catatan</span><input :name="`workers[${index}][notes]`" x-model="row.notes" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Catatan"></label></td>
                                            <td class="px-2 py-1.5"><div class="flex items-center gap-1"><button type="button" @click="move(index, -1)" :disabled="index === 0" title="Naikkan urutan" class="rounded-md border border-sky-200 px-2 py-1.5 text-xs font-bold text-sky-700 disabled:opacity-35">↑</button><button type="button" @click="move(index, 1)" :disabled="index === rows.length - 1" title="Turunkan urutan" class="rounded-md border border-sky-200 px-2 py-1.5 text-xs font-bold text-sky-700 disabled:opacity-35">↓</button><button type="button" @click="rows.splice(index, 1)" class="rounded-md border border-rose-200 px-2 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">Hapus</button></div></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>

                    <fieldset x-show="category === 'SPPD'" :disabled="category !== 'SPPD'" x-cloak x-data="{ rows: @js($travelRows), employees: @js($employeeOptions), dragIndex: null, addEmployee() { const employee = this.employees.find(item => String(item.id) === String(this.$refs.employeePicker.value)); if (!employee || this.rows.some(row => row.employee_id === employee.id)) return; this.rows.push({employee_id:employee.id, traveler_name:employee.name, destination:'', purpose:'', assignment_letter_number:'', assignment_letter_date:@js($transactionDateLimit), departure_date:@js($transactionDateLimit), return_date:@js($transactionDateLimit), transport_mode:'', amount:0, notes:'', position:employee.position}); this.$refs.employeePicker.value = ''; }, move(index, direction) { const target = index + direction; if (target < 0 || target >= this.rows.length) return; [this.rows[index], this.rows[target]] = [this.rows[target], this.rows[index]]; this.rows = [...this.rows]; }, dropAt(index) { if (this.dragIndex === null || this.dragIndex === index) return; const [row] = this.rows.splice(this.dragIndex, 1); this.rows.splice(index, 0, row); this.rows = [...this.rows]; this.dragIndex = null; } }" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><p class="text-[11px] font-bold uppercase tracking-wide text-sky-900">Pelaksana perjalanan dinas</p><p class="mt-0.5 text-xs text-sky-700">Satu pembayaran dapat memuat lebih dari satu pelaksana.</p></div>
                            <div class="flex flex-wrap gap-2"><select x-ref="employeePicker" class="min-w-56 rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-xs"><option value="">Pilih pegawai terdaftar</option><template x-for="employee in employees" :key="employee.id"><option :value="employee.id" x-text="employee.name + (employee.position ? ' · ' + employee.position : '')"></option></template></select><button type="button" @click="addEmployee()" class="rounded-md border border-sky-300 bg-white px-2.5 py-1.5 text-xs font-bold text-sky-800">Ambil pegawai</button><button type="button" @click="rows.push({traveler_name:'', destination:'', purpose:'', assignment_letter_number:'', assignment_letter_date:@js($transactionDateLimit), departure_date:@js($transactionDateLimit), return_date:@js($transactionDateLimit), transport_mode:'', amount:0, notes:''})" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-bold text-white">+ Manual</button></div>
                        </div>
                        <div class="mt-2 space-y-2">
                            <template x-for="(row, index) in rows" :key="index">
                                <div @dragover.prevent @drop.prevent="dropAt(index)" :class="dragIndex === index ? 'ring-2 ring-sky-400' : ''" class="grid gap-2 rounded-md border border-sky-200 bg-white p-2 md:grid-cols-6">
                                    <div class="flex items-center gap-2 md:col-span-6"><button type="button" draggable="true" @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'" @dragend="dragIndex = null" title="Seret untuk mengubah urutan" class="cursor-grab rounded border border-sky-200 bg-sky-50 px-2 py-1 text-slate-500 active:cursor-grabbing">⋮⋮</button><span class="text-xs font-bold text-sky-700" x-text="'Urutan ' + (index + 1)"></span></div>
                                    <label class="block md:col-span-2"><span class="text-[11px] font-semibold text-sky-900">Nama Pelaksana <span class="text-rose-600">*</span></span><input :name="`travels[${index}][traveler_name]`" x-model="row.traveler_name" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Nama pelaksana" required></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Tujuan</span><input :name="`travels[${index}][destination]`" x-model="row.destination" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Tujuan"></label>
                                    <label class="block md:col-span-2"><span class="text-[11px] font-semibold text-sky-900">Maksud Perjalanan</span><input :name="`travels[${index}][purpose]`" x-model="row.purpose" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Maksud perjalanan"></label>
                                    <label class="block md:col-span-2"><span class="text-[11px] font-semibold text-sky-900">No. Surat Tugas <span class="text-emerald-700">(otomatis)</span></span><input readonly :name="`travels[${index}][assignment_letter_number]`" x-model="row.assignment_letter_number" class="mt-1 w-full rounded-md border border-sky-200 bg-slate-100 px-2 py-1.5 text-sm" placeholder="Terbit setelah penomoran"></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Tgl Surat Tugas</span><input type="date" :name="`travels[${index}][assignment_letter_date]`" x-model="row.assignment_letter_date" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm"></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Transport</span><input :name="`travels[${index}][transport_mode]`" x-model="row.transport_mode" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Transport"></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Tgl Berangkat</span><input type="date" :name="`travels[${index}][departure_date]`" x-model="row.departure_date" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm"></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Tgl Pulang</span><input type="date" :name="`travels[${index}][return_date]`" x-model="row.return_date" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm"></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Nilai</span><input type="number" min="0" step="0.01" :name="`travels[${index}][amount]`" x-model="row.amount" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-right text-sm" placeholder="Nilai"></label>
                                    <label class="block md:col-span-2"><span class="text-[11px] font-semibold text-sky-900">Catatan</span><input :name="`travels[${index}][notes]`" x-model="row.notes" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Catatan"></label>
                                    <div class="flex items-end gap-1"><button type="button" @click="move(index, -1)" :disabled="index === 0" title="Naikkan urutan" class="rounded-md border border-sky-200 px-3 py-1.5 text-sm font-bold text-sky-700 disabled:opacity-35">↑</button><button type="button" @click="move(index, 1)" :disabled="index === rows.length - 1" title="Turunkan urutan" class="rounded-md border border-sky-200 px-3 py-1.5 text-sm font-bold text-sky-700 disabled:opacity-35">↓</button><button type="button" @click="rows.splice(index, 1)" class="rounded-md border border-rose-200 px-2 py-1.5 text-xs font-bold text-rose-700">Hapus</button></div>
                                </div>
                            </template>
                        </div>
                    </fieldset>

                    <fieldset x-show="category === 'HONOR_PEGAWAI'" :disabled="category !== 'HONOR_PEGAWAI'" x-cloak x-data="{ rows: @js($honorRows), transactionTotal: {{ (float) $transaction->gross_amount }}, dragIndex: null, get detailTotal() { return this.rows.reduce((total, row) => total + ((parseInt(row.work_days) || 0) * (Number(row.daily_rate) || 0)), 0); }, move(index, direction) { const target = index + direction; if (target < 0 || target >= this.rows.length) return; [this.rows[index], this.rows[target]] = [this.rows[target], this.rows[index]]; this.rows = [...this.rows]; }, dropAt(index) { if (this.dragIndex === null || this.dragIndex === index) return; const [row] = this.rows.splice(this.dragIndex, 1); this.rows.splice(index, 0, row); this.rows = [...this.rows]; this.dragIndex = null; } }" class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><p class="text-[11px] font-bold uppercase tracking-wide text-sky-900">Data penerima honor</p><p class="mt-0.5 text-xs text-sky-700">Gunakan baris ini untuk beberapa penerima honor dalam satu transaksi.</p></div>
                            <button type="button" @click="rows.push({name:'', job_description:'', work_days:1, daily_rate:0, is_receipt_recipient:false, notes:''})" class="rounded-md bg-sky-600 px-2.5 py-1.5 text-xs font-bold text-white">+ Penerima</button>
                        </div>
                        <div class="mt-2 space-y-2">
                            <template x-for="(row, index) in rows" :key="index">
                                <div @dragover.prevent @drop.prevent="dropAt(index)" :class="dragIndex === index ? 'ring-2 ring-sky-400' : ''" class="grid gap-2 rounded-md border border-sky-200 bg-white p-2 md:grid-cols-6">
                                    <div class="flex items-center gap-2 md:col-span-6"><button type="button" draggable="true" @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'" @dragend="dragIndex = null" title="Seret untuk mengubah urutan" class="cursor-grab rounded border border-sky-200 bg-sky-50 px-2 py-1 text-slate-500 active:cursor-grabbing">⋮⋮</button><span class="text-xs font-bold text-sky-700" x-text="'Urutan ' + (index + 1)"></span></div>
                                    <label class="block md:col-span-2"><span class="text-[11px] font-semibold text-sky-900">Nama Penerima <span class="text-rose-600">*</span></span><input :name="`workers[${index}][name]`" x-model="row.name" required class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Nama penerima"></label>
                                    <label class="block md:col-span-2"><span class="text-[11px] font-semibold text-sky-900">Jabatan/Jenis Honor <span class="text-rose-600">*</span></span><input :name="`workers[${index}][job_description]`" x-model="row.job_description" required class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Jabatan/jenis honor"></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Bulan/Kali <span class="text-rose-600">*</span></span><input type="number" min="1" step="1" required :name="`workers[${index}][work_days]`" :value="parseInt(row.work_days) || 1" @input="row.work_days = parseInt($event.target.value) || 1" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-right text-sm" placeholder="1"></label>
                                    <label class="block"><span class="text-[11px] font-semibold text-sky-900">Tarif <span class="text-rose-600">*</span></span><input type="hidden" :name="`workers[${index}][daily_rate]`" :value="row.daily_rate"><input type="text" inputmode="numeric" required :value="new Intl.NumberFormat('en-US').format(Number(row.daily_rate) || 0)" @input="row.daily_rate = Number($event.target.value.replace(/[^0-9]/g, '')); $event.target.value = new Intl.NumberFormat('en-US').format(row.daily_rate)" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-right text-sm" placeholder="0"></label>
                                    <label class="block md:col-span-5"><span class="text-[11px] font-semibold text-sky-900">Catatan</span><input :name="`workers[${index}][notes]`" x-model="row.notes" class="mt-1 w-full rounded-md border border-sky-200 px-2 py-1.5 text-sm" placeholder="Catatan"></label>
                                    <div class="flex items-end gap-1"><button type="button" @click="move(index, -1)" :disabled="index === 0" title="Naikkan urutan" class="rounded-md border border-sky-200 px-3 py-1.5 text-sm font-bold text-sky-700 disabled:opacity-35">↑</button><button type="button" @click="move(index, 1)" :disabled="index === rows.length - 1" title="Turunkan urutan" class="rounded-md border border-sky-200 px-3 py-1.5 text-sm font-bold text-sky-700 disabled:opacity-35">↓</button><button type="button" @click="rows.splice(index, 1)" class="rounded-md border border-rose-200 px-2 py-1.5 text-xs font-bold text-rose-700">Hapus</button></div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2"><p class="text-[11px] font-bold uppercase text-slate-500">Nilai transaksi</p><p class="mt-1 font-mono text-sm font-bold text-slate-900" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(transactionTotal)"></p></div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2"><p class="text-[11px] font-bold uppercase text-slate-500">Total rincian honor</p><p class="mt-1 font-mono text-sm font-bold" :class="Math.abs(detailTotal-transactionTotal)<0.01?'text-emerald-700':'text-rose-700'" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(detailTotal)"></p></div>
                            <div class="rounded-lg border px-3 py-2" :class="Math.abs(detailTotal-transactionTotal)<0.01?'border-emerald-200 bg-emerald-50':'border-rose-200 bg-rose-50'"><p class="text-[11px] font-bold uppercase" :class="Math.abs(detailTotal-transactionTotal)<0.01?'text-emerald-700':'text-rose-700'">Kesesuaian</p><p class="mt-1 text-sm font-bold" :class="Math.abs(detailTotal-transactionTotal)<0.01?'text-emerald-800':'text-rose-800'" x-text="Math.abs(detailTotal-transactionTotal)<0.01?'Sesuai':'Selisih Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(detailTotal-transactionTotal))"></p></div>
                        </div>
                    </fieldset>

                    <div x-show="category === 'JASA_LAINNYA'" x-cloak class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-sky-900">Data jasa lainnya</p>
                        <div class="mt-2 grid gap-2 md:grid-cols-4">
                            <div class="md:col-span-2"><label class="text-[11px] font-semibold text-sky-900">Uraian Jasa</label><textarea name="work_description" rows="2" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm" placeholder="Uraian jasa">{{ $transaction->work_description }}</textarea></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Lokasi/Unit Kerja</label><input name="work_location" value="{{ $transaction->work_location }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm" placeholder="Lokasi/unit"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tanggal Mulai</label><input type="date" name="work_started_at" value="{{ $transaction->work_started_at?->format('Y-m-d') ?: $transactionDateLimit }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-sky-900">Tanggal Selesai</label><input type="date" name="work_completed_at" value="{{ $transaction->work_completed_at?->format('Y-m-d') ?: $transactionDateLimit }}" max="{{ $transactionDateLimit }}" class="mt-1 w-full rounded-md border border-sky-200 px-2.5 py-1.5 text-sm"></div>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <button @disabled(($transaction->spjPackage && !$transaction->spjPackage->isEditable()) || $transaction->items->isEmpty()) class="inline-flex justify-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-violet-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                            {{ $transaction->spjPackage ? ($transaction->spjPackage->isEditable() ? 'Simpan Perbaikan Paket' : 'Paket Terkunci') : 'Buat Paket SPJ' }}
                        </button>
                        <p class="text-xs text-slate-500">{{ $transaction->spjPackage?->isEditable() ? 'Paket sudah ada tetapi belum dikunci. Koreksi data lalu simpan kembali.' : 'Isi hanya bagian yang sesuai kategori. Bagian lain otomatis disembunyikan.' }}</p>
                    </div>
                    @if($transaction->items->isEmpty())
                        <p class="mt-2 text-xs text-rose-600">Paket belum dapat dibuat karena rincian transaksi belum tersedia.</p>
                    @elseif(!$descriptionsComplete)
                        <p class="mt-2 text-xs text-amber-700">{{ $descriptionsFilled }} dari {{ $transaction->items->count() }} uraian item sudah lengkap. Simpan uraian di tabel bawah sebelum membuat paket.</p>
                    @endif
                    </fieldset>
                </form>

            </div>
        </section>

        <section id="rincian-transaksi" class="order-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-bold text-slate-800">Rincian Barang dan Jasa</h2><p class="mt-1 text-base text-slate-500">Item pembentuk transaksi {{ $transaction->no_bukti }}. Uraian manual diprioritaskan bila tersedia.</p></div><span class="rounded-lg bg-indigo-50 px-3 py-2 text-base font-bold text-indigo-700">{{ $transaction->items->count() }} baris detail</span></div>
            <form method="POST" action="{{ route('transactions.spj-descriptions.update', $transaction->id) }}">@csrf @method('PUT')
            <fieldset @disabled($transaction->spjPackage && !$transaction->spjPackage->isEditable()) class="disabled:cursor-not-allowed disabled:opacity-60">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-base">
                    <thead class="bg-slate-50"><tr><th class="w-14 px-5 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500">No</th><th class="min-w-[320px] px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Uraian Barang/Jasa untuk SPJ</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Rekening</th><th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Volume</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Satuan</th><th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Harga Satuan</th><th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Nilai</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($transaction->items as $index => $item)
                            <tr class="transition hover:bg-indigo-50/50"><td class="px-5 py-3.5 text-center text-xs font-semibold text-slate-400">{{ $index + 1 }}</td><td class="max-w-xl px-4 py-3.5"><p class="mb-1 text-xs text-slate-400">Asli: {{ $item->description }}</p><input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}"><input name="items[{{ $index }}][item_description]" value="{{ $item->item_description ?: $item->description }}" class="w-full rounded-md border border-indigo-200 bg-white px-3 py-2 text-base focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: Buku tulis"></td><td class="px-4 py-3.5 font-mono text-xs text-sky-700">{{ $item->account_code ?: $transaction->account_code ?: '—' }}</td><td class="px-4 py-3.5 text-right font-medium text-slate-700">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td><td class="px-4 py-3.5 text-slate-600">{{ $item->unit ?: '—' }}</td><td class="whitespace-nowrap px-4 py-3.5 text-right text-slate-600">{{ $rupiah($item->unit_price) }}</td><td class="whitespace-nowrap px-5 py-3.5 text-right font-bold text-slate-800">{{ $rupiah($item->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">Rincian transaksi belum tersedia.</p><p class="mt-1 text-base text-slate-500">Periksa kembali hasil sinkronisasi BKU untuk nomor bukti ini.</p></td></tr>
                        @endforelse
                    </tbody>
                    @if($transaction->items->isNotEmpty())<tfoot class="border-t-2 border-slate-200 bg-slate-50"><tr><td colspan="6" class="px-5 py-4 text-right text-base font-bold uppercase tracking-wide text-slate-600">Total rincian</td><td class="px-5 py-4 text-right text-base font-bold text-indigo-700">{{ $rupiah($totalItems) }}</td></tr></tfoot>@endif
                </table>
            </div>
            @if($transaction->items->isNotEmpty())<div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-3"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-indigo-700">Simpan Uraian Barang/Jasa</button></div>@endif
            </fieldset>
            </form>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow"><h2 class="font-bold text-slate-800">Rincian Pajak</h2><div class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-base">@foreach(['PPN' => $transaction->ppn, 'PPh 21' => $transaction->pph21, 'PPh 22' => $transaction->pph22, 'PPh 23' => $transaction->pph23, 'PPh 4(2)' => $transaction->pph4, 'SSPD' => $transaction->sspd] as $label => $value)<div class="flex justify-between gap-3 border-b border-slate-100 pb-2"><span class="text-slate-500">{{ $label }}</span><span class="font-semibold text-slate-800">{{ $rupiah($value) }}</span></div>@endforeach</div></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow"><h2 class="font-bold text-slate-800">Informasi Dokumen SPJ</h2><dl class="mt-4 space-y-3 text-base"><div class="flex justify-between gap-4"><dt class="text-slate-500">Nomor SPJ</dt><dd class="text-right font-semibold {{ $transaction->spjPackage?->status === 'CANCELLED' ? 'text-rose-700 line-through' : 'text-slate-800' }}">{{ $transaction->spjPackage?->document_number ?: 'Belum ditetapkan' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Status paket</dt><dd class="font-semibold {{ $transaction->spjPackage?->status === 'CANCELLED' ? 'text-rose-700' : 'text-slate-800' }}">{{ $transaction->spjPackage?->status === 'CANCELLED' ? 'Nomor dibatalkan' : ($transaction->spjPackage?->status ?: 'Belum dibuat') }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Referensi pembayaran</dt><dd class="text-right font-semibold text-slate-800">{{ $transaction->payment_reference ?: 'Belum ada referensi' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Pembelian SIPLah</dt><dd class="font-semibold text-slate-800">{{ $transaction->is_siplah ? 'Ya' : 'Tidak' }}</dd></div></dl></article>
        </section>
    </div>
</x-layouts.tailwind-app>
