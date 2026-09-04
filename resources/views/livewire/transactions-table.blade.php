<div class="space-y-6" x-data="{
    editorOpen: false,
    editorAction: '',
    editor: { spj_category: '', payment_description: '', payment_method: '', payment_reference: '', receipt_recipient_name: '', no_bukti: '' },
    openEditorFromButton(button) {
        this.editorAction = button.dataset.action || '';
        this.editor = {
            spj_category: button.dataset.spjCategory || '',
            payment_description: button.dataset.paymentDescription || button.dataset.description || '',
            payment_method: button.dataset.paymentMethod || '',
            payment_reference: button.dataset.paymentReference || '',
            receipt_recipient_name: button.dataset.receiptRecipient || '',
            no_bukti: button.dataset.noBukti || '',
        };
        this.editorOpen = true;
        this.$nextTick(() => this.$refs.category?.focus());
    },
    closeEditor() {
        this.editorOpen = false;
    },
}">
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
    @php($spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
        'HONOR_PEGAWAI' => 'Honor Pegawai',
        default => str_replace('_', ' ', (string) $value),
    })

    <x-page-header
        title="Transaksi & SPJ"
        subtitle="Mulai dari transaksi, lengkapi data SPJ, lalu lanjutkan ke paket dokumen tanpa berpindah alur."
        kicker="BUKU KAS & SPJ"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('synced-data.show', 'bku')" wire:navigate>Lihat BKU Mentah</x-ui.button>
            <x-ui.button :href="route('spj.index')">Buka ruang kerja SPJ →</x-ui.button>
        </x-slot:actions>

        <div class="border-b border-slate-100 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Total Tahunan {{ $activeYear->year }}</div>
        <div class="grid divide-y divide-slate-100 sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Transaksi</p><p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($stats->count, 0, ',', '.') }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Nilai Bruto</p><p class="mt-1 text-xl font-bold text-indigo-700">{{ $rupiah($stats->gross) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Pajak</p><p class="mt-1 text-xl font-bold text-amber-600">{{ $rupiah($stats->tax) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Dibayarkan</p><p class="mt-1 text-xl font-bold text-emerald-700">{{ $rupiah($stats->net) }}</p></div>
        </div>
    </x-page-header>

    <section class="ui-filter-panel">
        <div class="border-b border-[var(--ui-line)] px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">Filter Periode</div>
        <div class="grid gap-3 p-5 md:grid-cols-5 md:items-end">
            <x-ui.field label="Cari transaksi" for="transaction-search" class="md:col-span-2">
                <x-ui.input id="transaction-search" wire:model.live.debounce.400ms="q" placeholder="Nomor bukti, uraian, penerima..." />
            </x-ui.field>
            <x-ui.field label="Status" for="transaction-status">
                <x-ui.select id="transaction-status" wire:model.live="status">
                    <option value="">Semua status</option>
                    @foreach($statuses as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Triwulan" for="transaction-quarter">
                <x-ui.select id="transaction-quarter" wire:model.live="quarter">
                    <option value="">Semua triwulan</option>
                    <option value="1">Triwulan 1</option>
                    <option value="2">Triwulan 2</option>
                    <option value="3">Triwulan 3</option>
                    <option value="4">Triwulan 4</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="button" variant="secondary" wire:click="clearFilters">Reset</x-ui.button>
        </div>
        <div class="border-y border-[var(--ui-line)] px-5 py-3 text-xs font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">Subtotal Periode Terpilih</div>
        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Transaksi</p><p class="mt-1 text-xl font-bold" style="color: var(--ui-fg)">{{ number_format($filteredStats->count, 0, ',', '.') }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Nilai Bruto</p><p class="mt-1 text-xl font-bold" style="color: var(--ui-fg)">{{ $rupiah($filteredStats->gross) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Pajak</p><p class="mt-1 text-xl font-bold" style="color: var(--ui-fg)">{{ $rupiah($filteredStats->tax) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Dibayarkan</p><p class="mt-1 text-xl font-bold" style="color: var(--ui-fg)">{{ $rupiah($filteredStats->net) }}</p></div>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
        <x-ui.toolbar class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
            <div>
                <h2 class="font-bold" style="color: var(--ui-fg)">Daftar Transaksi SPJ</h2>
                <p class="mt-1 text-base" style="color: var(--ui-fg-muted)">Pilih satu transaksi untuk membuka detail.</p>
            </div>
            <x-slot:actions>
                <div class="min-w-[9rem]">
                    <label for="transaction-per-page" class="sr-only">Baris per halaman</label>
                    <x-ui.select id="transaction-per-page" wire:model.live="perPage" class="!w-auto">
                        <option value="15">15 baris</option>
                        <option value="25">25 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                        <option value="all">Semua</option>
                    </x-ui.select>
                </div>
            </x-slot:actions>
        </x-ui.toolbar>

        <div class="grid gap-3 p-4 lg:hidden">
            @forelse($transactions as $transaction)
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow transition hover:shadow-md" wire:key="transaction-card-{{ $transaction->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <a href="{{ route('transactions.show', $transaction) }}" wire:navigate class="font-mono text-base font-bold" style="color: var(--theme-content-accent)">{{ $transaction->no_bukti }}</a>
                        @if($transaction->spjPackage?->status === 'CANCELLED')
                            <x-ui.status-badge status="CANCELLED" label="Nomor dibatalkan" size="xs" />
                        @elseif($transaction->spjPackage?->document_number)
                            <x-ui.status-badge status="NUMBERED" label="Bernomor" size="xs" />
                            <p class="mt-1 font-mono text-[11px] font-bold" style="color: var(--ui-fg)">{{ $transaction->spjPackage->document_number }}</p>
                        @elseif($transaction->spjPackage)
                            <x-ui.status-badge status="DRAFT" label="Draft paket" size="xs" />
                        @elseif($transaction->items_count > 0)
                            <x-ui.status-badge status="READY" label="Siap dibuat" size="xs" />
                        @else
                            <x-ui.status-badge status="DRAFT" label="Belum lengkap" size="xs" />
                        @endif
                    </div>
                    <p class="mt-1.5 line-clamp-2 text-base font-semibold" style="color: var(--ui-fg)">{{ $transaction->description ?: $transaction->payment_description ?: 'Tanpa uraian' }}</p>
                    @if($transaction->spj_category)<p class="mt-1 text-xs font-semibold" style="color: var(--theme-content-accent)">SPJ: {{ $spjTypeLabel($transaction->spj_category) }}</p>@endif
                    <x-ui.button type="button" variant="secondary" x-on:click="openEditorFromButton($el)" :disabled="$transaction->spjPackage && !$transaction->spjPackage->isEditable()" data-action="{{ route('transactions.manual-description.update', $transaction->id) }}" data-spj-category="{{ $transaction->spj_category }}" data-payment-description="{{ $transaction->payment_description }}" data-description="{{ $transaction->description }}" data-payment-method="{{ $this->paymentMethodFor($transaction) }}" data-payment-reference="{{ $transaction->payment_reference }}" data-receipt-recipient="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}" data-no-bukti="{{ $transaction->no_bukti }}" class="mt-2"><x-ui-icon name="edit" class="h-3.5 w-3.5" />{{ $transaction->payment_description || $transaction->spj_category || $transaction->description ? 'Ubah data SPJ' : 'Isi data SPJ' }}</x-ui.button>
                    <p class="mt-1 truncate text-xs" style="color: var(--ui-fg-muted)">BKU: {{ $transaction->recipient_name ?: 'Penerima belum diisi' }} · Kuitansi: {{ $transaction->effective_receipt_recipient_name ?: 'Belum diisi' }}</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">@if(strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING')<x-ui.status-badge status="SOURCE_MISSING" label="Tidak muncul di sync terakhir" size="xs" />@endif @if($transaction->requires_reconciliation)<x-ui.status-badge status="REQUIRES_RECONCILIATION" label="Perlu rekonsiliasi" size="xs" />@endif</div>
                    <div class="mt-3 flex items-center justify-between border-t pt-3">
                        <div><p class="text-xs" style="color: var(--ui-fg-muted)">Bruto</p><p class="text-base font-bold" style="color: var(--ui-fg)">{{ $rupiah($transaction->gross_amount) }}</p></div>
                        <x-ui.button :href="route('transactions.show', $transaction)" wire:navigate>Buka</x-ui.button>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed p-8 text-center"><p class="font-semibold" style="color: var(--ui-fg)">Transaksi belum ditemukan.</p><p class="mt-1 text-base" style="color: var(--ui-fg-muted)">Coba ubah filter atau sinkron ARKAS.</p></div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <table data-pagination="server" class="min-w-full divide-y divide-slate-200 text-base">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Bukti / Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Uraian ARKAS & Penerima</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Uraian Pembayaran / Kategori SPJ</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Kegiatan / Rekening</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500">Detail</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Bruto</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Pajak</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($transactions as $transaction)
                        <tr class="transition hover:bg-indigo-50/50" wire:key="transaction-row-{{ $transaction->id }}">
                            <td class="px-5 py-4"><a href="{{ route('transactions.show', $transaction) }}" wire:navigate class="font-mono text-base font-bold" style="color: var(--theme-content-accent)">{{ $transaction->no_bukti }}</a><p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia' }} · {{ $transaction->payment_method ?: 'Cara bayar belum diisi' }}</p></td>
                            <td class="max-w-sm px-4 py-4"><p class="truncate font-semibold" style="color: var(--ui-fg)">{{ $transaction->description ?: 'Tanpa uraian' }}</p><p class="mt-1 truncate text-xs" style="color: var(--ui-fg-muted)">BKU: {{ $transaction->recipient_name ?: 'Penerima belum diisi' }}</p><p class="mt-1 truncate text-xs" style="color: var(--theme-content-accent)">Kuitansi: {{ $transaction->effective_receipt_recipient_name ?: 'Belum diisi' }}</p>@if(strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING' || $transaction->requires_reconciliation)<div class="mt-2 flex flex-wrap gap-1.5">@if(strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING')<x-ui.status-badge status="SOURCE_MISSING" label="Tidak muncul di sync" size="xs" />@endif @if($transaction->requires_reconciliation)<x-ui.status-badge status="REQUIRES_RECONCILIATION" label="Rekonsiliasi" size="xs" />@endif</div>@endif</td>
                            <td class="min-w-[220px] max-w-sm px-4 py-4"><p class="line-clamp-2 text-sm {{ $transaction->payment_description || $transaction->description ? 'font-medium' : '' }}" style="color: {{ $transaction->payment_description || $transaction->description ? 'var(--ui-fg)' : 'var(--ui-fg-muted)' }}">{{ $transaction->payment_description ?: $transaction->description ?: 'Belum diisi' }}</p>@if($transaction->spj_category)<p class="mt-1 text-xs font-semibold" style="color: var(--theme-content-accent)">{{ $spjTypeLabel($transaction->spj_category) }}</p>@endif<x-ui.button type="button" variant="secondary" x-on:click="openEditorFromButton($el)" :disabled="$transaction->spjPackage && !$transaction->spjPackage->isEditable()" data-action="{{ route('transactions.manual-description.update', $transaction->id) }}" data-spj-category="{{ $transaction->spj_category }}" data-payment-description="{{ $transaction->payment_description }}" data-description="{{ $transaction->description }}" data-payment-method="{{ $this->paymentMethodFor($transaction) }}" data-payment-reference="{{ $transaction->payment_reference }}" data-receipt-recipient="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}" data-no-bukti="{{ $transaction->no_bukti }}" class="mt-2"><x-ui-icon name="edit" class="h-3.5 w-3.5" />{{ $transaction->payment_description || $transaction->spj_category || $transaction->description ? 'Ubah data SPJ' : 'Isi data SPJ' }}</x-ui.button></td>
                            <td class="max-w-xs px-4 py-4"><p class="font-mono text-xs font-semibold" style="color: var(--theme-content-accent)">{{ $transaction->activity_code ?: '—' }}</p><p class="mt-1 truncate text-xs" style="color: var(--ui-fg-muted)">{{ $transaction->account_code ?: 'Rekening belum tersedia' }}</p></td>
                            <td class="px-4 py-4 text-center">
                                <x-ui.badge variant="neutral">{{ $transaction->items_count }} item</x-ui.badge>
                                <div class="mt-1.5">
                                    @if($transaction->spjPackage?->status === 'CANCELLED')
                                        <x-ui.status-badge status="CANCELLED" label="Nomor dibatalkan" size="xs" />
                                    @elseif($transaction->spjPackage?->document_number)
                                        <x-ui.status-badge status="NUMBERED" label="Bernomor" size="xs" />
                                        <p class="mt-1 font-mono text-[11px] font-bold" style="color: var(--ui-fg)">{{ $transaction->spjPackage->document_number }}</p>
                                    @elseif($transaction->spjPackage)
                                        <x-ui.status-badge status="DRAFT" label="Draft paket" size="xs" />
                                    @elseif($transaction->items_count > 0)
                                        <x-ui.status-badge status="READY" label="Siap dibuat" size="xs" />
                                    @else
                                        <x-ui.status-badge status="DRAFT" label="Belum lengkap" size="xs" />
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-semibold" style="color: var(--ui-fg)">{{ $rupiah($transaction->gross_amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-medium text-amber-700">{{ $rupiah($transaction->tax_total) }}</td>
                            <td class="px-5 py-4 text-right"><x-ui.button variant="secondary" :href="route('transactions.show', $transaction)" wire:navigate>Buka detail</x-ui.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center"><p class="font-semibold" style="color: var(--ui-fg)">Transaksi belum ditemukan.</p><p class="mt-1 text-base" style="color: var(--ui-fg-muted)">Jalankan Sinkron Semua ARKAS atau ubah filter pencarian.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 bg-slate-50/30 px-5 py-4">
            {{ $transactions->links() }}
        </div>
    </section>

    <div x-show="editorOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" x-on:click.self="closeEditor">
        <form method="POST" x-bind:action="editorAction" class="w-full max-w-xl rounded-xl bg-white p-5 shadow-2xl">
            @csrf
            @method('PUT')
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-bold tracking-[.14em] text-violet-600">TRANSAKSI <span x-text="editor.no_bukti"></span></p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">Data SPJ Transaksi</h2>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi uraian dan kategori SPJ tanpa mengubah data asli hasil sinkronisasi.</p>
                    </div>
                    <button type="button" x-on:click="closeEditor" class="text-xl text-slate-400 hover:text-slate-700">×</button>
                </div>

                <label class="mt-4 block text-sm font-bold text-slate-700">Kategori SPJ</label>
                <select name="spj_category" x-model="editor.spj_category" x-ref="category" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Pilih kategori SPJ</option>
                    <option value="BARANG">Barang</option>
                    <option value="KONSUMSI">Konsumsi</option>
                    <option value="PEMELIHARAAN">Pemeliharaan</option>
                    <option value="JASA_LAINNYA">Jasa Lainnya</option>
                    <option value="SPPD">SPPD</option>
                    <option value="HONOR_PEGAWAI">Honor Pegawai</option>
                </select>
                @error('form.spj_category')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror

                <label class="mt-4 block text-sm font-bold text-slate-700">Uraian Pembayaran</label>
                <textarea name="payment_description" x-model="editor.payment_description" rows="5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: Pembelian alat tulis kantor untuk mendukung pembelajaran dan administrasi sekolah."></textarea>
                @error('form.payment_description')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-bold text-slate-700">Metode Pembayaran</label>
                        <select name="payment_method" x-model="editor.payment_method" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="transfer_bank">Transfer Bank (CMS / Non Tunai)</option>
                            <option value="siplah">SiPLah Kemdikbud</option>
                            <option value="tunai">Tunai Kas BOS</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-bold text-slate-700">Referensi Bayar</label><input name="payment_reference" x-model="editor.payment_reference" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"></div>
                    <div class="sm:col-span-2"><label class="block text-sm font-bold text-slate-700">Penerima Kuitansi</label><input name="receipt_recipient_name" x-model="editor.receipt_recipient_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Boleh berbeda dari penerima BKU/ARKAS"></div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" x-on:click="closeEditor" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700">Batal</button>
                    <button class="rounded-md bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">Simpan Data SPJ</button>
                </div>
            </form>
        </div>
</div>
