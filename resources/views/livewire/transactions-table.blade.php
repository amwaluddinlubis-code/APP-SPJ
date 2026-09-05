<div class="space-y-6" x-data="{
    editorOpen: false,
    warningOpen: false,
    warningMessage: '',
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
    closeEditor() { this.editorOpen = false; },
    showDescriptionWarning() {
        this.warningMessage = 'Lengkapi Deskripsi Belanja Terlebih Dahulu';
        this.warningOpen = true;
    },
    closeWarning() { this.warningOpen = false; },
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

    <section class="overflow-hidden border border-slate-200 bg-white shadow-sm">
        <x-ui.toolbar class="border-b-0 bg-slate-50 px-4 py-3 sm:px-5">
            <div>
                <h2 class="font-bold" style="color: var(--ui-fg)">Daftar Transaksi SPJ</h2>
                <p class="mt-0.5 text-sm" style="color: var(--ui-fg-muted)">Urutan kerja: perlu deskripsi → siap detail → draft paket → bernomor → final.</p>
            </div>
            <x-slot:actions>
                <div class="min-w-[8.5rem]">
                    <label for="transaction-per-page" class="sr-only">Baris per halaman</label>
                    <x-ui.select id="transaction-per-page" wire:model.live="perPage" class="!w-auto !py-1.5 text-sm">
                        <option value="15">15 baris</option>
                        <option value="25">25 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                        <option value="all">Semua</option>
                    </x-ui.select>
                </div>
            </x-slot:actions>
        </x-ui.toolbar>

        <div class="grid gap-2 border-t border-slate-200 p-3 lg:hidden">
            @forelse($transactions as $transaction)
                @php($workStatus = $this->workStatusFor($transaction))
                <article class="border border-slate-200 bg-white px-3 py-2.5" wire:key="transaction-card-{{ $transaction->id }}">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="font-mono text-xs font-bold text-slate-500">#{{ $transaction->id }}</span>
                            <x-ui.status-badge :status="$workStatus['status']" :label="$workStatus['label']" size="xs" />
                        </div>
                        <span class="text-[11px]" style="color: var(--ui-fg-muted)">{{ $transaction->transaction_date?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-xs">
                        <span class="font-mono font-bold" style="color: var(--theme-content-accent)">{{ $transaction->no_bukti }}</span>
                        @if($this->paymentMethodFor($transaction) === 'siplah')<x-ui.badge class="text-[10px]">SiPLah</x-ui.badge>@endif
                    </div>
                    <p class="mt-1 truncate text-sm font-semibold" style="color: var(--ui-fg)">{{ $transaction->description ?: 'Tanpa uraian ARKAS' }}</p>
                    <p class="mt-0.5 truncate text-xs" style="color: {{ filled($transaction->payment_description) ? 'var(--theme-content-accent)' : 'var(--ui-fg-muted)' }}">SPJ: {{ $transaction->payment_description ?: 'Deskripsi belanja belum diisi' }}</p>
                    <div class="mt-1 flex items-center justify-between gap-2 text-[11px]" style="color: var(--ui-fg-muted)">
                        <span class="truncate">{{ $transaction->spj_category ? $spjTypeLabel($transaction->spj_category).' · ' : '' }}{{ $transaction->items_count }} item · {{ $transaction->effective_receipt_recipient_name ?: $transaction->recipient_name ?: 'Penerima belum diisi' }}</span>
                        <span class="whitespace-nowrap font-semibold" style="color: var(--ui-fg)">{{ $rupiah($transaction->gross_amount) }}</span>
                    </div>
                    <div class="mt-2 flex justify-end gap-1.5 border-t border-slate-100 pt-2">
                        <button type="button" x-on:click="openEditorFromButton($el)" @disabled($transaction->spjPackage && !$transaction->spjPackage->isEditable()) data-action="{{ route('transactions.manual-description.update', $transaction->id) }}" data-spj-category="{{ $transaction->spj_category }}" data-payment-description="{{ $transaction->payment_description }}" data-description="{{ $transaction->description }}" data-payment-method="{{ $this->paymentMethodFor($transaction) }}" data-payment-reference="{{ $transaction->payment_reference }}" data-receipt-recipient="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}" data-no-bukti="{{ $transaction->no_bukti }}" title="Ubah data SPJ" aria-label="Ubah data SPJ" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"><x-ui-icon name="edit" class="h-4 w-4" /></button>
                        @if(filled($transaction->payment_description))
                            <a href="{{ route('transactions.show', $transaction) }}" wire:navigate title="Buka detail" aria-label="Buka detail" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700"><x-ui-icon name="document" class="h-4 w-4" /></a>
                        @else
                            <button type="button" x-on:click="showDescriptionWarning()" title="Buka detail — deskripsi belanja belum lengkap" aria-label="Buka detail belum tersedia" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-300 transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-600"><x-ui-icon name="document" class="h-4 w-4" /></button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="border border-dashed p-8 text-center"><p class="font-semibold" style="color: var(--ui-fg)">Transaksi belum ditemukan.</p><p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Coba ubah filter atau sinkron ARKAS.</p></div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto border-t border-slate-200 lg:block">
            <table data-pagination="server" class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="w-[180px] px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">ID / Status</th>
                        <th class="px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Uraian & SPJ</th>
                        <th class="w-[180px] px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Kegiatan / Rekening</th>
                        <th class="w-[150px] px-4 py-2 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500">Nilai</th>
                        <th class="w-[92px] px-4 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($transactions as $transaction)
                        @php($workStatus = $this->workStatusFor($transaction))
                        <tr class="transition hover:bg-indigo-50/40" wire:key="transaction-row-{{ $transaction->id }}">
                            <td class="px-4 py-2.5 align-middle">
                                <div class="flex items-center gap-2"><span class="font-mono text-xs font-bold text-slate-500">#{{ $transaction->id }}</span><x-ui.status-badge :status="$workStatus['status']" :label="$workStatus['label']" size="xs" /></div>
                                <div class="mt-1 flex items-center gap-1.5 text-[11px]" style="color: var(--ui-fg-muted)"><span class="font-mono font-bold" style="color: var(--theme-content-accent)">{{ $transaction->no_bukti }}</span><span>·</span><span>{{ $transaction->transaction_date?->format('d/m/Y') ?? '—' }}</span>@if($this->paymentMethodFor($transaction) === 'siplah')<span>· SiPLah</span>@endif</div>
                            </td>
                            <td class="max-w-0 px-4 py-2.5 align-middle">
                                <p class="truncate font-semibold" style="color: var(--ui-fg)">{{ $transaction->description ?: 'Tanpa uraian ARKAS' }}</p>
                                <p class="mt-0.5 truncate text-xs" style="color: {{ filled($transaction->payment_description) ? 'var(--theme-content-accent)' : 'var(--ui-fg-muted)' }}">SPJ: {{ $transaction->payment_description ?: 'Deskripsi belanja belum diisi' }}</p>
                                <p class="mt-0.5 truncate text-[11px]" style="color: var(--ui-fg-muted)">{{ $transaction->spj_category ? $spjTypeLabel($transaction->spj_category).' · ' : '' }}{{ $transaction->items_count }} item · Penerima: {{ $transaction->effective_receipt_recipient_name ?: $transaction->recipient_name ?: 'Belum diisi' }}@if($transaction->requires_reconciliation) · Rekonsiliasi@endif</p>
                            </td>
                            <td class="px-4 py-2.5 align-middle"><p class="truncate font-mono text-xs font-semibold" style="color: var(--theme-content-accent)">{{ $transaction->activity_code ?: '—' }}</p><p class="mt-0.5 truncate text-[11px]" style="color: var(--ui-fg-muted)">{{ $transaction->account_code ?: 'Rekening belum tersedia' }}</p></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right align-middle"><p class="font-semibold" style="color: var(--ui-fg)">{{ $rupiah($transaction->gross_amount) }}</p><p class="mt-0.5 text-[11px] text-amber-700">Pajak {{ $rupiah($transaction->tax_total) }}</p></td>
                            <td class="px-4 py-2.5 align-middle">
                                <div class="flex items-center justify-center gap-1.5" aria-label="Aksi transaksi {{ $transaction->no_bukti }}">
                                    <button type="button" x-on:click="openEditorFromButton($el)" @disabled($transaction->spjPackage && !$transaction->spjPackage->isEditable()) data-action="{{ route('transactions.manual-description.update', $transaction->id) }}" data-spj-category="{{ $transaction->spj_category }}" data-payment-description="{{ $transaction->payment_description }}" data-description="{{ $transaction->description }}" data-payment-method="{{ $this->paymentMethodFor($transaction) }}" data-payment-reference="{{ $transaction->payment_reference }}" data-receipt-recipient="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}" data-no-bukti="{{ $transaction->no_bukti }}" title="Ubah data SPJ" aria-label="Ubah data SPJ" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"><x-ui-icon name="edit" class="h-4 w-4" /></button>
                                    @if(filled($transaction->payment_description))
                                        <a href="{{ route('transactions.show', $transaction) }}" wire:navigate title="Buka detail" aria-label="Buka detail" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700"><x-ui-icon name="document" class="h-4 w-4" /></a>
                                    @else
                                        <button type="button" x-on:click="showDescriptionWarning()" title="Buka detail — deskripsi belanja belum lengkap" aria-label="Buka detail belum tersedia" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-300 transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-600"><x-ui-icon name="document" class="h-4 w-4" /></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center"><p class="font-semibold" style="color: var(--ui-fg)">Transaksi belum ditemukan.</p><p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Jalankan Sinkron Semua ARKAS atau ubah filter pencarian.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-2 border-t border-slate-200 bg-slate-50 px-4 py-2.5 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p style="color: var(--ui-fg-muted)">Menampilkan <span class="font-semibold" style="color: var(--ui-fg)">{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }}</span> dari <span class="font-semibold" style="color: var(--ui-fg)">{{ $transactions->total() }}</span> transaksi</p>
            @if($transactions->hasPages())
                <nav class="flex items-center gap-1" aria-label="Navigasi halaman transaksi">
                    <button type="button" wire:click="previousPage" @disabled($transactions->onFirstPage()) class="inline-flex h-8 items-center rounded-md border border-slate-200 bg-white px-2.5 font-semibold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">‹</button>
                    @for($page = max(1, $transactions->currentPage() - 2); $page <= min($transactions->lastPage(), $transactions->currentPage() + 2); $page++)
                        <button type="button" wire:click="gotoPage({{ $page }})" aria-current="{{ $transactions->currentPage() === $page ? 'page' : 'false' }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 font-semibold {{ $transactions->currentPage() === $page ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">{{ $page }}</button>
                    @endfor
                    <button type="button" wire:click="nextPage" @disabled(!$transactions->hasMorePages()) class="inline-flex h-8 items-center rounded-md border border-slate-200 bg-white px-2.5 font-semibold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">›</button>
                </nav>
            @endif
        </div>
    </section>

    <div x-show="warningOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="transaction-warning-title" x-on:click.self="closeWarning" x-on:keydown.escape.window="closeWarning">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl"><div class="flex items-start gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">!</div><div><p class="text-xs font-bold uppercase tracking-wide text-amber-700">Warning</p><h2 id="transaction-warning-title" class="mt-1 text-lg font-bold text-slate-900" x-text="warningMessage"></h2><p class="mt-2 text-sm text-slate-500">Gunakan ikon Ubah Data SPJ untuk mengisi deskripsi belanja sebelum membuka detail transaksi.</p></div></div><div class="mt-5 flex justify-end"><button type="button" x-on:click="closeWarning" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">Mengerti</button></div></div>
    </div>

    <div x-show="editorOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" x-on:click.self="closeEditor">
        <form method="POST" x-bind:action="editorAction" class="w-full max-w-xl rounded-xl bg-white p-5 shadow-2xl">
            @csrf
            @method('PUT')
            <div class="flex items-start justify-between gap-4"><div><p class="text-[11px] font-bold tracking-[.14em] text-violet-600">TRANSAKSI <span x-text="editor.no_bukti"></span></p><h2 class="mt-1 text-lg font-bold text-slate-900">Data SPJ Transaksi</h2><p class="mt-1 text-sm text-slate-500">Lengkapi uraian dan kategori SPJ tanpa mengubah data asli hasil sinkronisasi.</p></div><button type="button" x-on:click="closeEditor" class="text-xl text-slate-400 hover:text-slate-700">×</button></div>
            <label class="mt-4 block text-sm font-bold text-slate-700">Kategori SPJ</label>
            <select name="spj_category" x-model="editor.spj_category" x-ref="category" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Pilih kategori SPJ</option><option value="BARANG">Barang</option><option value="KONSUMSI">Konsumsi</option><option value="PEMELIHARAAN">Pemeliharaan</option><option value="JASA_LAINNYA">Jasa Lainnya</option><option value="SPPD">SPPD</option><option value="HONOR_PEGAWAI">Honor Pegawai</option></select>
            @error('form.spj_category')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            <label class="mt-4 block text-sm font-bold text-slate-700">Uraian Pembayaran</label>
            <textarea name="payment_description" x-model="editor.payment_description" rows="5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: Pembelian alat tulis kantor untuk mendukung pembelajaran dan administrasi sekolah."></textarea>
            @error('form.payment_description')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            <div class="mt-4 grid gap-3 sm:grid-cols-2"><div><label class="block text-sm font-bold text-slate-700">Metode Pembayaran</label><select name="payment_method" x-model="editor.payment_method" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="transfer_bank">Transfer Bank (CMS / Non Tunai)</option><option value="siplah">SiPLah Kemdikbud</option><option value="tunai">Tunai Kas BOS</option></select></div><div><label class="block text-sm font-bold text-slate-700">Referensi Bayar</label><input name="payment_reference" x-model="editor.payment_reference" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"></div><div class="sm:col-span-2"><label class="block text-sm font-bold text-slate-700">Penerima Kuitansi</label><input name="receipt_recipient_name" x-model="editor.receipt_recipient_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Boleh berbeda dari penerima BKU/ARKAS"></div></div>
            <div class="mt-5 flex justify-end gap-2"><button type="button" x-on:click="closeEditor" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700">Batal</button><button class="rounded-md bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700">Simpan Data SPJ</button></div>
        </form>
    </div>
</div>
