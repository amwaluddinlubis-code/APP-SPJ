<x-layouts.tailwind-app>
    @php($transaction = $package->transaction)
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
    @php($spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
        'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
        default => str_replace('_', ' ', (string) $value),
    })
    <div class="space-y-5">
        {{-- Navigasi --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('spj.index', ['tab' => 'persiapan']) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-base font-semibold text-slate-700 shadow hover:bg-slate-50">← Semua paket</a>
            <a href="{{ route('transactions.show', $transaction->id) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-base font-semibold text-indigo-700 hover:bg-indigo-100">Lihat transaksi</a>
        </div>

        {{-- Header Paket --}}
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <p class="text-[11px] font-bold tracking-[.16em] text-sky-200">PAKET DOKUMEN SPJ</p>
                <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="font-mono text-2xl font-bold sm:text-3xl">{{ $transaction->no_bukti }}</h1>
                        <p class="mt-1.5 text-base text-indigo-100 line-clamp-2">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian transaksi belum tersedia.' }}</p>
                    </div>
                    <div class="rounded-lg bg-white/10 px-3 py-2.5 text-left lg:text-right ring-1 ring-white/20">
                        <p class="text-[11px] font-semibold text-indigo-200">Nomor Dokumen SPJ</p>
                        <p class="mt-0.5 font-mono text-base font-bold">{{ $package->document_number ?: 'Belum ditetapkan' }}</p>
                    </div>
                </div>
            </div>
            <div class="grid divide-y divide-slate-100 md:grid-cols-3 md:divide-x md:divide-y-0">
                <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Periode</p><p class="mt-1 text-base font-semibold text-slate-800">{{ $package->quarter_code }} · {{ $package->semester_code }}</p></div>
                <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Penerima</p><p class="mt-1 text-base font-semibold text-slate-800">{{ $transaction->recipient_name ?: 'Belum diisi' }}</p></div>
                <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Nilai Dibayarkan</p><p class="mt-1 text-base font-semibold text-emerald-700">{{ $rupiah($transaction->net_amount) }}</p></div>
            </div>
        </section>

        {{-- Validasi --}}
        <section class="overflow-hidden rounded-xl border {{ $validationIssues ? 'border-amber-200' : 'border-emerald-200' }} bg-white shadow">
            <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-base font-bold text-slate-800">Validasi Sebelum Cetak</h2><p class="mt-0.5 text-base {{ $validationIssues ? 'text-amber-700' : 'text-emerald-700' }}">{{ $validationIssues ? count($validationIssues).' data wajib perlu dilengkapi sebelum PDF dibuat.' : 'Semua data wajib lengkap. Paket siap diunduh sebagai PDF.' }}</p></div>
                @if($validationIssues)<span class="w-fit rounded-md bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-700">Belum siap cetak</span>@else<form method="POST" action="{{ route('spj.download', $package->id) }}" target="_blank">@csrf<button class="rounded-md bg-emerald-600 px-3.5 py-1.5 text-base font-bold text-white shadow hover:bg-emerald-700">Pratinjau Paket PDF</button></form>@endif
            </div>
            @if($validationIssues)<div class="divide-y divide-amber-100 border-t border-amber-100 bg-amber-50/40">@foreach($validationIssues as $issue)<div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-base"><div><span class="font-bold text-amber-800">{{ $issue['label'] }}</span><span class="ml-1.5 text-amber-700">{{ $issue['message'] }}</span></div><a href="{{ $issue['url'] }}" class="text-xs font-bold text-indigo-700 hover:text-indigo-900">Buka transaksi →</a></div>@endforeach</div>@endif
        </section>

        {{-- TABS: Rincian | Isian Manual | Kesiapan | Penomoran --}}
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow" id="spj-tabs">
            {{-- Tab Header --}}
            <div class="border-b border-slate-200 bg-slate-50/70">
                <nav class="flex gap-1 overflow-x-auto px-2 py-1 text-base" aria-label="Tabs">
                    <button type="button" data-tab="rincian" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600 hover:text-slate-800">📦 Rincian <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px]">{{ $transaction->items->count() }}</span></button>
                    <button type="button" data-tab="isian" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600 hover:text-slate-800">✏️ Isian Manual</button>
                    <button type="button" data-tab="kesiapan" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600 hover:text-slate-800">✅ Kesiapan</button>
                    <button type="button" data-tab="penomoran" class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border border-transparent data-[active=true]:bg-white data-[active=true]:border-slate-200 data-[active=true]:text-indigo-700 data-[active=true]:shadow text-slate-600 hover:text-slate-800">🔢 Penomoran @if($package->document_number)<span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] text-emerald-700">OK</span>@else<span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-700">Belum</span>@endif</button>
                </nav>
            </div>

            {{-- Panel: Rincian Paket --}}
            <div data-panel="rincian" class="tab-panel">
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

            {{-- Panel: Isian Manual — Alpine x-data for live UX --}}
            <div data-panel="isian" class="tab-panel hidden" x-data="{saving:false}">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-base font-bold text-slate-800">Isian Manual Paket SPJ</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Hanya isian kuning yang wajib. Bagian biru tampil sesuai kategori.</p>
                    <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">Kuning · wajib</span>
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">Biru · opsional</span>
                    </div>
                </div>
                @php($workerRows = $transaction->workers->concat(collect(array_fill(0, max(2, 6 - $transaction->workers->count()), null))))
                @php($selectedSpjType = strtoupper((string) old('spj_category', $transaction->spj_category ?: $transaction->spj_category)))
                <form id="spj-manual-form" method="POST" action="{{ route('spj.update', $package->id) }}" class="space-y-4 p-4" @submit="saving=true">@csrf @method('PUT')
                    <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-bold text-amber-900">Kategori SPJ <span class="text-rose-600">*</span></label>
                                <select id="spj-type" name="spj_category" class="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-base focus:border-amber-400 focus:ring-1 focus:ring-amber-200">
                                    <option value="">Pilih kategori</option>
                                    @foreach(['BARANG','BELANJA_MODAL','KONSUMSI','JASA_HONORARIUM','UPAH','PEMELIHARAAN','PERJALANAN_DINAS','LAINNYA'] as $value)
                                        <option value="{{ $value }}" @selected(in_array($selectedSpjType, ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) && in_array(strtoupper((string) $value), ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) || old('spj_category', $transaction->spj_category ?: $transaction->spj_category) === $value)>{{ $spjTypeLabel($value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-amber-900">Subkategori</label>
                                <input name="spj_category" value="{{ old('spj_category', $transaction->spj_category ?: $transaction->spj_category ?: '') }}" class="mt-1 w-full rounded-md border border-amber-300 bg-white px-2.5 py-1.5 text-base placeholder:text-slate-400 focus:border-amber-400 focus:ring-1 focus:ring-amber-200" placeholder="ATK / konsumsi / honor">
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="text-xs font-semibold text-slate-700">Uraian Dokumen</label><textarea name="description" rows="2" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200">{{ $transaction->document_description }}</textarea></div>
                        <div><label class="text-xs font-semibold text-slate-700">Untuk Pembayaran</label><textarea name="payment_description" rows="2" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200">{{ $transaction->payment_description }}</textarea></div>
                    </div>

                    <div><label class="text-xs font-semibold text-slate-700">Referensi Bayar</label><input name="payment_reference" value="{{ $transaction->payment_reference }}" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-base placeholder:text-slate-400 focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200" placeholder="No. transfer / pesanan"></div>

                    <div data-spj-section="BARANG BELANJA_MODAL" class="rounded-lg border border-sky-200 bg-sky-50/60 p-3">
                        <h3 class="text-xs font-bold text-sky-900">Invoice / Pesanan <span class="text-[11px] font-normal text-sky-700">(barang/modal)</span></h3>
                        <div class="mt-2 grid gap-3 sm:grid-cols-3">
                            <div><label class="text-xs font-medium text-sky-900">No. Invoice</label><input name="invoice_number" value="{{ $transaction->invoice_number }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                            <div><label class="text-xs font-medium text-sky-900">Tgl Invoice</label><input type="date" name="invoice_date" value="{{ $transaction->invoice_date?->format('Y-m-d') }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                            <div><label class="text-xs font-medium text-sky-900">Status</label><input name="invoice_status" value="{{ $transaction->invoice_status }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base" placeholder="LUNAS"></div>
                        </div>
                    </div>

                    <div data-spj-section="UPAH JASA_HONORARIUM PEMELIHARAAN" class="rounded-lg border border-sky-200 bg-sky-50/60 p-3">
                        <h3 class="text-xs font-bold text-sky-900">Pelaksanaan Pekerjaan <span class="text-[11px] font-normal">(upah/pemeliharaan)</span></h3>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <div><label class="text-xs font-medium text-sky-900">Uraian Pekerjaan</label><textarea name="work_description" rows="2" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-3 py-2 text-base">{{ $transaction->work_description }}</textarea></div>
                            <div><label class="text-xs font-medium text-sky-900">Lokasi</label><input name="work_location" value="{{ $transaction->work_location }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                            <div><label class="text-xs font-medium text-sky-900">Tgl Mulai</label><input type="date" name="work_started_at" value="{{ $transaction->work_started_at?->format('Y-m-d') }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                            <div><label class="text-xs font-medium text-sky-900">Tgl Selesai</label><input type="date" name="work_completed_at" value="{{ $transaction->work_completed_at?->format('Y-m-d') }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                        </div>
                    </div>

                    <div data-spj-section="PEMELIHARAAN" class="rounded-lg border border-sky-200 bg-sky-50/60 p-3">
                        <h3 class="text-xs font-bold text-sky-900">SPK & Penandatangan</h3>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <div><label class="text-xs font-medium text-sky-900">No. SPK</label><input name="spk_number" value="{{ $transaction->spk_number }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                            <div><label class="text-xs font-medium text-sky-900">Tgl SPK</label><input type="date" name="spk_date" value="{{ $transaction->spk_date?->format('Y-m-d') }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                            <div><label class="text-xs font-medium text-sky-900">Nama Penandatangan</label><input name="signatory_name" value="{{ $transaction->signatory_name }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base"></div>
                            <div><label class="text-xs font-medium text-sky-900">Peran</label><input name="signatory_role" value="{{ $transaction->signatory_role }}" class="mt-1 w-full rounded-md border border-sky-200 bg-white px-2.5 py-1.5 text-base" placeholder="Pelaksana"></div>
                        </div>
                    </div>

                    <div data-spj-section="UPAH JASA_HONORARIUM PEMELIHARAAN" class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50/30">
                        <div class="border-b border-amber-100 bg-amber-50/60 px-3 py-2.5">
                            <h3 class="text-xs font-bold text-amber-900">Rincian Pekerja / Upah</h3>
                            <p class="mt-0.5 text-[11px] text-amber-800">Centang 1 penerima kuitansi. Hari × tarif dihitung otomatis.</p>
                        </div>
                        <div id="workers-desktop" class="hidden overflow-x-auto lg:block">
                            <table class="min-w-[760px] w-full text-base">
                                <thead class="bg-amber-100/60 text-[11px] font-bold uppercase tracking-wide text-amber-900">
                                    <tr><th class="px-2.5 py-1.5 text-left">Nama</th><th class="px-2.5 py-1.5 text-left">Pekerjaan</th><th class="px-2.5 py-1.5 text-right">Hari</th><th class="px-2.5 py-1.5 text-right">Tarif</th><th class="px-2.5 py-1.5 text-center">Kuitansi</th><th class="px-2.5 py-1.5 text-left">Catatan</th></tr>
                                </thead>
                                <tbody class="divide-y divide-amber-100 bg-white">
                                    @foreach($workerRows as $index => $worker)
                                    <tr>
                                        <td class="px-2 py-1.5"><input name="workers[{{ $index }}][name]" value="{{ $worker?->name }}" class="w-full rounded-md border border-amber-200 bg-white px-2 py-1 text-base" placeholder="Nama"></td>
                                        <td class="px-2 py-1.5"><input name="workers[{{ $index }}][job_description]" value="{{ $worker?->job_description }}" class="w-full rounded-md border border-amber-200 bg-white px-2 py-1 text-base" placeholder="Tukang"></td>
                                        <td class="px-2 py-1.5"><input type="number" min="0" step=".5" name="workers[{{ $index }}][work_days]" value="{{ $worker?->work_days }}" class="w-20 rounded-md border border-amber-200 bg-white px-2 py-1 text-right text-base"></td>
                                        <td class="px-2 py-1.5"><input type="number" min="0" step="1" name="workers[{{ $index }}][daily_rate]" value="{{ $worker?->daily_rate }}" class="w-24 rounded-md border border-amber-200 bg-white px-2 py-1 text-right text-base"></td>
                                        <td class="px-2 py-1.5 text-center"><input type="hidden" name="workers[{{ $index }}][is_receipt_recipient]" value="0"><input type="checkbox" name="workers[{{ $index }}][is_receipt_recipient]" value="1" @checked($worker?->is_receipt_recipient) class="rounded border-amber-300 text-amber-600"></td>
                                        <td class="px-2 py-1.5"><input name="workers[{{ $index }}][notes]" value="{{ $worker?->notes }}" class="w-full rounded-md border border-amber-200 bg-white px-2 py-1 text-base"></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div id="workers-mobile" class="space-y-2 p-2 lg:hidden">
                            @foreach($workerRows as $index => $worker)
                            <div class="rounded-lg border border-amber-200 bg-white p-3 space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="text-[11px] font-semibold text-amber-900">Nama</label><input name="workers[{{ $index }}][name]" value="{{ $worker?->name }}" class="mt-1 w-full rounded-md border border-amber-200 bg-white px-2.5 py-1.5 text-base"></div>
                                    <div><label class="text-[11px] font-semibold text-amber-900">Pekerjaan</label><input name="workers[{{ $index }}][job_description]" value="{{ $worker?->job_description }}" class="mt-1 w-full rounded-md border border-amber-200 bg-white px-2.5 py-1.5 text-base"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="text-[11px] font-semibold text-amber-900">Hari</label><input type="number" min="0" step=".5" name="workers[{{ $index }}][work_days]" value="{{ $worker?->work_days }}" class="mt-1 w-full rounded-md border border-amber-200 bg-white px-2.5 py-1.5 text-base text-right"></div>
                                    <div><label class="text-[11px] font-semibold text-amber-900">Tarif/Hari</label><input type="number" min="0" step="1" name="workers[{{ $index }}][daily_rate]" value="{{ $worker?->daily_rate }}" class="mt-1 w-full rounded-md border border-amber-200 bg-white px-2.5 py-1.5 text-base text-right"></div>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-amber-900"><input type="hidden" name="workers[{{ $index }}][is_receipt_recipient]" value="0"><input type="checkbox" name="workers[{{ $index }}][is_receipt_recipient]" value="1" @checked($worker?->is_receipt_recipient) class="rounded border-amber-300 text-amber-600"> Penerima kuitansi</label>
                                    <input name="workers[{{ $index }}][notes]" value="{{ $worker?->notes }}" class="flex-1 rounded-md border border-amber-200 bg-white px-2.5 py-1.5 text-base" placeholder="Catatan">
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end pt-1"><button :disabled="saving" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-1.5 text-base font-bold text-white shadow hover:bg-indigo-700 disabled:opacity-60"><span x-show="saving" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span> <span x-text="saving ? 'Menyimpan...' : 'Simpan Isian Paket'"></span></button></div>
                </form>
            </div>

            {{-- Panel: Kesiapan --}}
            <div data-panel="kesiapan" class="tab-panel hidden p-4">
                <h2 class="text-base font-bold text-slate-800">Kesiapan Paket</h2>
                <p class="mt-1 text-xs text-slate-500">Checklist kelengkapan sebelum cetak.</p>
                <div class="mt-4 space-y-2">
                    @foreach([['Transaksi dan nomor bukti', true], ['Rincian barang/jasa', $transaction->items->isNotEmpty()], ['Penerima pembayaran', filled($transaction->recipient_name)], ['Kegiatan dan rekening', filled($transaction->activity_code) && filled($transaction->account_code)], ['Nomor dokumen SPJ', filled($package->document_number)]] as [$label, $ready])
                        <div class="flex items-center justify-between rounded-lg border {{ $ready ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/50' }} px-3 py-2.5">
                            <span class="text-base font-medium {{ $ready ? 'text-emerald-800' : 'text-amber-800' }}">{{ $label }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $ready ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $ready ? '✓ Siap' : '• Perlu' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Panel: Penomoran --}}
            <div data-panel="penomoran" class="tab-panel hidden p-4">
                <h2 class="text-base font-bold text-slate-800">Penomoran Dokumen</h2>
                <p class="mt-1 text-xs text-slate-500">Format: nomor/SPJ-BOSP/NPSN/triwulan/tahun.</p>
                @if($package->document_number)
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Sudah ditetapkan</p>
                        <p class="mt-1 font-mono text-base font-bold text-emerald-800 break-all">{{ $package->document_number }}</p>
                        <p class="mt-2 text-xs text-emerald-700">Nomor: {{ $package->quarter_code }} · {{ $package->semester_code }}</p>
                    </div>
                @else
                    <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                        <p class="text-base font-medium text-slate-700">Belum bernomor</p>
                        <p class="mt-1 text-xs text-slate-500">Klik tombol di bawah untuk generate nomor otomatis.</p>
                        <form class="mt-4" method="POST" action="{{ route('spj.assign-number', $package->id) }}">@csrf<button class="rounded-md bg-violet-600 px-4 py-2 text-base font-bold text-white shadow hover:bg-violet-700">Tetapkan nomor SPJ</button></form>
                    </div>
                @endif
            </div>
        </section>

        @if($templates->isNotEmpty())
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
                <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-base font-bold text-slate-800">Dokumen dari Template</h2><p class="mt-0.5 text-xs text-slate-500">Unduh Word/Excel dari placeholder <code>&#123;&#123;PLACEHOLDER&#125;&#125;</code>.</p></div>
                <div class="divide-y divide-slate-100">@foreach($templates as $template)<div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"><div><p class="text-base font-semibold text-slate-800">{{ $template->name }}</p><p class="mt-0.5 text-[11px] font-mono text-violet-700">{{ $template->document_type }} · {{ strtoupper($template->format) }}</p></div><div class="flex flex-wrap items-center gap-2">@if(strtolower($template->format) === 'xlsx')<button type="button" data-template-preview="{{ route('spj.preview-template', [$package->id, $template->id]) }}" data-template-name="{{ $template->name }}" class="rounded-md border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-700 hover:bg-sky-100">Pratinjau</button>@endif @if($validationIssues)<span class="text-xs font-semibold text-amber-700">Lengkapi validasi</span>@else<form method="POST" action="{{ route('spj.download-template', [$package->id, $template->id]) }}">@csrf<button class="rounded-md border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 hover:bg-violet-100">Unduh {{ strtoupper($template->format) }}</button></form>@endif</div></div>@endforeach</div>
            </section>
        @endif
    </div>

    <div id="template-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-3 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="template-preview-title">
        <section class="flex h-[94vh] w-full max-w-[1500px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3"><div><p class="text-[11px] font-bold tracking-[.14em] text-sky-600">PRATINJAU TEMPLATE</p><h2 id="template-preview-title" class="text-sm font-bold text-slate-900">Dokumen SPJ</h2></div><div class="flex items-center gap-2"><button type="button" id="template-preview-print" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">Cetak / PDF</button><button type="button" data-close-template-preview class="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-950">Tutup</button></div></header>
            <div class="min-h-0 flex-1 bg-slate-100 p-2"><iframe id="template-preview-frame" title="Pratinjau template SPJ" class="h-full w-full rounded-md border border-slate-200 bg-white"></iframe></div>
        </section>
    </div>

    <script>
        (() => {
            // Tabs logic
            const tabBtns = document.querySelectorAll('[data-tab]');
            const panels = document.querySelectorAll('[data-panel]');
            const storageKey = 'spj-tab-{{ $package->id }}';
            const setActive = (name) => {
                tabBtns.forEach(b => b.dataset.active = (b.dataset.tab === name).toString());
                panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
                localStorage.setItem(storageKey, name);
                history.replaceState(null, '', '#'+name);
            };
            tabBtns.forEach(btn => btn.addEventListener('click', () => setActive(btn.dataset.tab)));
            const initial = location.hash.replace('#','') || localStorage.getItem(storageKey) || 'rincian';
            setActive(['rincian','isian','kesiapan','penomoran'].includes(initial) ? initial : 'rincian');

            // Spj-type conditional sections
            const category = document.getElementById('spj-type');
            const sections = document.querySelectorAll('[data-spj-section]');
            const refresh = () => {
                const value = (category?.value || '').toUpperCase();
                sections.forEach((section) => {
                    const allowed = section.dataset.spjSection.split(' ');
                    section.classList.toggle('hidden', !allowed.includes(value));
                });
            };
            category?.addEventListener('change', () => {
                refresh();
                // jika user pilih kategori yang butuh isian, auto pindah ke tab isian
                if (category.value) setActive('isian');
            });
            refresh();

            // Jika ada validationIssues, arahkan ke isian jika belum siap
            @if($validationIssues)
                // tetap di rincian biar user lihat dulu, tapi highlight isian tab
                document.querySelector('[data-tab="isian"]')?.classList.add('ring-1','ring-amber-300');
            @endif

            // Fix duplicate workers: hanya set yang terlihat yang submit (light)
            const formW = document.getElementById('spj-manual-form');
            const syncWorkers = () => {
                const isDesktop = window.innerWidth >= 1024;
                document.querySelectorAll('#workers-desktop input, #workers-desktop select, #workers-desktop textarea').forEach(el => el.disabled = !isDesktop);
                document.querySelectorAll('#workers-mobile input, #workers-mobile select, #workers-mobile textarea').forEach(el => el.disabled = isDesktop);
            };
            window.addEventListener('resize', syncWorkers);
            syncWorkers();
            if (formW) formW.addEventListener('submit', syncWorkers);

            const previewModal = document.getElementById('template-preview-modal');
            const previewFrame = document.getElementById('template-preview-frame');
            const previewTitle = document.getElementById('template-preview-title');
            const closePreview = () => { previewModal.classList.add('hidden'); previewModal.classList.remove('flex'); previewFrame.src = 'about:blank'; };
            document.querySelectorAll('[data-template-preview]').forEach((button) => button.addEventListener('click', () => {
                previewTitle.textContent = button.dataset.templateName || 'Pratinjau Template';
                previewFrame.src = button.dataset.templatePreview;
                previewModal.classList.remove('hidden'); previewModal.classList.add('flex');
            }));
            document.querySelectorAll('[data-close-template-preview]').forEach((button) => button.addEventListener('click', closePreview));
            previewModal?.addEventListener('click', (event) => { if (event.target === previewModal) closePreview(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !previewModal?.classList.contains('hidden')) closePreview(); });
            document.getElementById('template-preview-print')?.addEventListener('click', () => previewFrame.contentWindow?.print());
        })();
    </script>
</x-layouts.tailwind-app>
