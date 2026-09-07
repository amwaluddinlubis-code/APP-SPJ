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
            <x-ui.button variant="secondary" icon="database" :href="route('synced-data.show', 'bku')" wire:navigate>
                Lihat BKU Mentah
            </x-ui.button>
            <x-ui.button icon="document" :href="route('spj.index')">
                Buka Ruang Kerja SPJ
            </x-ui.button>
        </x-slot:actions>

        <div class="flex items-center gap-2 border-b border-[var(--ui-line)] px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            <x-ui-icon name="calendar" class="h-4 w-4" />
            <span>Total Tahunan {{ $activeYear->year }}</span>
        </div>
        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
            <x-stat-item
                label="Transaksi"
                :value="number_format($stats->count, 0, ',', '.')"
                hint="Total transaksi tahun aktif"
                value-class="text-slate-800"
                icon="transaction"
                icon-class="text-slate-600"
            />
            <x-stat-item
                label="Nilai Bruto"
                :value="$rupiah($stats->gross)"
                hint="Total nilai transaksi"
                value-class="text-indigo-700"
                icon="budget"
                icon-class="text-indigo-600"
            />
            <x-stat-item
                label="Pajak"
                :value="$rupiah($stats->tax)"
                hint="Total pajak tercatat"
                value-class="text-amber-600"
                icon="tax"
                icon-class="text-amber-600"
            />
            <x-stat-item
                label="Dibayarkan"
                :value="$rupiah($stats->net)"
                hint="Nilai bersih setelah pajak"
                value-class="text-emerald-700"
                icon="balance"
                icon-class="text-emerald-600"
            />
        </div>
    </x-page-header>

    <section class="ui-filter-panel">
        <div class="flex items-center gap-2 border-b border-[var(--ui-line)] px-5 py-3 text-sm font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">
            <x-ui.icon name="filter" size="sm" />
            <span>Filter Periode</span>
        </div>
        <div class="grid gap-3 p-5 md:grid-cols-5 md:items-end">
            <x-ui.field label="Cari transaksi" for="transaction-search" class="md:col-span-2">
                <x-ui.input id="transaction-search" wire:model.live.debounce.400ms="q" placeholder="Nomor bukti, uraian, penerima..." />
            </x-ui.field>
            <x-ui.field label="Status" for="transaction-status">
                <x-ui.select id="transaction-status" wire:model.live="status">
                    <option value="">Semua status</option>
                    @foreach($statuses as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
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
            <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="clearFilters">
                Reset Filter
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2 border-y border-[var(--ui-line)] px-5 py-3 text-sm font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">
            <x-ui-icon name="report" class="h-4 w-4" />
            <span>Subtotal Periode Terpilih</span>
        </div>
        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
            <x-stat-item
                label="Transaksi"
                :value="number_format($filteredStats->count, 0, ',', '.')"
                hint="Hasil filter aktif"
                value-class="text-slate-800"
                icon="transaction"
                icon-class="text-slate-600"
            />
            <x-stat-item
                label="Nilai Bruto"
                :value="$rupiah($filteredStats->gross)"
                hint="Total nilai hasil filter"
                value-class="text-indigo-700"
                icon="budget"
                icon-class="text-indigo-600"
            />
            <x-stat-item
                label="Pajak"
                :value="$rupiah($filteredStats->tax)"
                hint="Total pajak hasil filter"
                value-class="text-amber-600"
                icon="tax"
                icon-class="text-amber-600"
            />
            <x-stat-item
                label="Dibayarkan"
                :value="$rupiah($filteredStats->net)"
                hint="Nilai bersih hasil filter"
                value-class="text-emerald-700"
                icon="balance"
                icon-class="text-emerald-600"
            />
        </div>
    </section>

    <section class="overflow-hidden border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
        <x-ui.toolbar class="border-b-0 bg-[var(--ui-surface-soft)] px-4 py-3 sm:px-5">
            <div>
                <h2 class="flex items-center gap-2 text-base font-bold" style="color: var(--ui-fg)">
                    <x-ui-icon name="transaction" class="h-5 w-5" />
                    <span>Daftar Transaksi SPJ</span>
                </h2>
                <p class="mt-0.5 text-sm" style="color: var(--ui-fg-muted)">Semua transaksi yang belum bernomor tetap di depan; yang sudah bernomor dipindahkan ke belakang.</p>
            </div>
            <x-slot:actions>
                <div class="flex min-w-[9rem] items-center gap-2">
                    <x-ui-icon name="queue" class="h-4 w-4" style="color: var(--ui-fg-muted)" />
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

        {{-- Mobile cards --}}
        <div class="grid gap-2 border-t border-[var(--ui-line)] p-3 lg:hidden">
            @if($transactions->count() > 0)
                @foreach($transactions as $transaction)
                    @php($workStatus = $this->workStatusFor($transaction))
                    <article class="border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-3" wire:key="transaction-card-{{ $transaction->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="font-mono text-[13px] font-bold text-slate-500">#{{ $transaction->id }}</span>
                                <x-ui.status-badge :status="$workStatus['status']" :label="$workStatus['label']" size="xs" />
                            </div>
                            <span class="text-[13px]" style="color: var(--ui-fg-muted)">{{ $transaction->transaction_date?->format('d/m/Y') ?? '—' }}</span>
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-[13px]">
                            <span class="font-mono font-bold" style="color: var(--theme-content-accent)">{{ $transaction->no_bukti }}</span>
                            @if($this->paymentMethodFor($transaction) === 'siplah')
                                <span class="text-[13px]" style="color: var(--ui-fg-muted)">· SiPLah</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-sm font-semibold" style="color: var(--ui-fg)">{{ $transaction->description ?: 'Tanpa uraian ARKAS' }}</p>
                        <p class="mt-0.5 truncate text-[13px]" style="color: {{ filled($transaction->payment_description) ? 'var(--theme-content-accent)' : 'var(--ui-fg-muted)' }}">SPJ: {{ $transaction->payment_description ?: 'Deskripsi belanja belum diisi' }}</p>
                        <div class="mt-1 flex items-start justify-between gap-3 text-[13px]" style="color: var(--ui-fg-muted)">
                            <span class="min-w-0 truncate">{{ $transaction->spj_category ? $spjTypeLabel($transaction->spj_category).' · ' : '' }}{{ $transaction->items_count }} item · {{ $transaction->effective_receipt_recipient_name ?: $transaction->recipient_name ?: 'Penerima belum diisi' }}</span>
                            <div class="shrink-0 text-right">
                                <p class="font-semibold" style="color: var(--ui-fg)">Total Transaksi: {{ $rupiah($transaction->gross_amount) }}</p>
                                <p class="mt-0.5 font-semibold text-amber-700">Pajak: {{ $rupiah($transaction->tax_total) }}</p>
                            </div>
                        </div>
                        <div class="transaction-action-cell mt-3 flex justify-end gap-2 border-t border-[var(--ui-line)] pt-3">
                            <button
                                type="button"
                                x-on:click="openEditorFromButton($el)"
                                @disabled($transaction->spjPackage && !$transaction->spjPackage->isEditable())
                                data-action="{{ route('transactions.manual-description.update', $transaction->id) }}"
                                data-spj-category="{{ $transaction->spj_category }}"
                                data-payment-description="{{ $transaction->payment_description }}"
                                data-description="{{ $transaction->description }}"
                                data-payment-method="{{ $this->paymentMethodFor($transaction) }}"
                                data-payment-reference="{{ $transaction->payment_reference }}"
                                data-receipt-recipient="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}"
                                data-no-bukti="{{ $transaction->no_bukti }}"
                                title="Ubah data SPJ"
                                class="transaction-action-button transaction-action-edit"
                            >
                                <x-ui.icon name="edit" size="sm" />
                                <span>Ubah</span>
                            </button>
                            @if(filled($transaction->payment_description))
                                <a href="{{ route('transactions.show', $transaction) }}" wire:navigate title="Buka detail" class="transaction-action-button transaction-action-detail">
                                    <x-ui.icon name="document" size="sm" />
                                    <span>Detail</span>
                                </a>
                            @else
                                <button type="button" x-on:click="showDescriptionWarning()" title="Buka detail — deskripsi belanja belum lengkap" class="transaction-action-button transaction-action-pending">
                                    <x-ui.icon name="warning" size="sm" />
                                    <span>Detail</span>
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            @else
                <div class="border border-dashed p-8 text-center">
                    <x-ui-icon name="inbox" class="mx-auto h-7 w-7" style="color: var(--ui-fg-muted)" />
                    <p class="mt-2 text-sm font-semibold" style="color: var(--ui-fg)">Transaksi belum ditemukan.</p>
                    <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Coba ubah filter atau sinkron ARKAS.</p>
                </div>
            @endif
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto border-t border-[var(--ui-line)] lg:block">
            <table data-pagination="server" class="w-full table-fixed text-sm">
                <colgroup>
                    <col class="w-[180px]">
                    <col>
                    <col class="w-[220px]">
                    <col class="w-[200px]">
                </colgroup>
                <thead class="bg-[var(--ui-surface-soft)]">
                    <tr class="border-b border-[var(--ui-line)]">
                        <th class="px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">ID / Status</th>
                        <th class="px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Uraian / Referensi</th>
                        <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Nilai</th>
                        <th class="transaction-action-column px-4 py-3 text-center text-[13px] font-bold uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                    @if($transactions->count() > 0)
                        @foreach($transactions as $transaction)
                            @php($workStatus = $this->workStatusFor($transaction))
                            <tr class="transition hover:bg-indigo-50/40" wire:key="transaction-row-{{ $transaction->id }}">
                                <td class="px-4 py-3 align-middle">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="shrink-0 font-mono text-[13px] font-bold text-slate-500">#{{ $transaction->id }}</span>
                                        <div class="min-w-0"><x-ui.status-badge :status="$workStatus['status']" :label="$workStatus['label']" size="xs" /></div>
                                    </div>
                                    <p class="mt-1 truncate text-[13px]" style="color: var(--ui-fg-muted)">
                                        <span class="font-mono font-bold" style="color: var(--theme-content-accent)">{{ $transaction->no_bukti }}</span>
                                        · {{ $transaction->transaction_date?->format('d/m/Y') ?? '—' }}{{ $this->paymentMethodFor($transaction) === 'siplah' ? ' · SiPLah' : '' }}
                                    </p>
                                </td>
                                <td class="min-w-0 px-4 py-3 align-middle">
                                    <p class="truncate text-sm font-semibold" style="color: var(--ui-fg)" title="{{ $transaction->description }}">{{ $transaction->description ?: 'Tanpa uraian ARKAS' }}</p>
                                    <p class="mt-1 truncate text-[13px]" style="color: {{ filled($transaction->payment_description) ? 'var(--theme-content-accent)' : 'var(--ui-fg-muted)' }}" title="{{ $transaction->payment_description }}">SPJ: {{ $transaction->payment_description ?: 'Deskripsi belanja belum diisi' }}</p>
                                    <p class="mt-1 truncate text-[13px]" style="color: var(--ui-fg-muted)">{{ $transaction->activity_code ?: '—' }} · {{ $transaction->account_code ?: 'Rekening belum tersedia' }} · {{ $transaction->spj_category ? $spjTypeLabel($transaction->spj_category).' · ' : '' }}{{ $transaction->items_count }} item · {{ $transaction->effective_receipt_recipient_name ?: $transaction->recipient_name ?: 'Penerima belum diisi' }}{{ $transaction->requires_reconciliation ? ' · Rekonsiliasi' : '' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-middle">
                                    <p class="text-sm font-semibold" style="color: var(--ui-fg)"><span class="font-medium" style="color: var(--ui-fg-muted)">Total Transaksi:</span> {{ $rupiah($transaction->gross_amount) }}</p>
                                    <p class="mt-1 text-[13px] font-semibold text-amber-700"><span class="font-medium">Pajak:</span> {{ $rupiah($transaction->tax_total) }}</p>
                                </td>
                                <td class="transaction-action-column px-3 py-3 align-middle">
                                    <div class="flex items-center justify-center gap-2" aria-label="Aksi transaksi {{ $transaction->no_bukti }}">
                                        <button
                                            type="button"
                                            x-on:click="openEditorFromButton($el)"
                                            @disabled($transaction->spjPackage && !$transaction->spjPackage->isEditable())
                                            data-action="{{ route('transactions.manual-description.update', $transaction->id) }}"
                                            data-spj-category="{{ $transaction->spj_category }}"
                                            data-payment-description="{{ $transaction->payment_description }}"
                                            data-description="{{ $transaction->description }}"
                                            data-payment-method="{{ $this->paymentMethodFor($transaction) }}"
                                            data-payment-reference="{{ $transaction->payment_reference }}"
                                            data-receipt-recipient="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}"
                                            data-no-bukti="{{ $transaction->no_bukti }}"
                                            title="Ubah data SPJ"
                                            class="transaction-action-button transaction-action-edit"
                                        >
                                            <x-ui.icon name="edit" size="sm" />
                                            <span>Ubah</span>
                                        </button>
                                        @if(filled($transaction->payment_description))
                                            <a href="{{ route('transactions.show', $transaction) }}" wire:navigate title="Buka detail" class="transaction-action-button transaction-action-detail">
                                                <x-ui.icon name="document" size="sm" />
                                                <span>Detail</span>
                                            </a>
                                        @else
                                            <button type="button" x-on:click="showDescriptionWarning()" title="Buka detail — deskripsi belanja belum lengkap" class="transaction-action-button transaction-action-pending">
                                                <x-ui.icon name="warning" size="sm" />
                                                <span>Detail</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center">
                                <x-ui-icon name="inbox" class="mx-auto h-7 w-7" style="color: var(--ui-fg-muted)" />
                                <p class="mt-2 text-sm font-semibold" style="color: var(--ui-fg)">Transaksi belum ditemukan.</p>
                                <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Jalankan Sinkron Semua ARKAS atau ubah filter pencarian.</p>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-2 border-t border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3 text-[13px] sm:flex-row sm:items-center sm:justify-between">
            <p style="color: var(--ui-fg-muted)">Menampilkan <span class="font-semibold" style="color: var(--ui-fg)">{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }}</span> dari <span class="font-semibold" style="color: var(--ui-fg)">{{ $transactions->total() }}</span> transaksi</p>
            @if($transactions->hasPages())
                <nav class="flex items-center gap-1" aria-label="Navigasi halaman transaksi">
                    <button type="button" wire:click="previousPage" @disabled($transactions->onFirstPage()) class="inline-flex h-9 items-center rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 font-semibold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Halaman sebelumnya"><x-ui.icon name="chevron-left" size="sm" /></button>
                    @for($page = max(1, $transactions->currentPage() - 2); $page <= min($transactions->lastPage(), $transactions->currentPage() + 2); $page++)
                        <button type="button" wire:click="gotoPage({{ $page }})" aria-current="{{ $transactions->currentPage() === $page ? 'page' : 'false' }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-2 font-semibold {{ $transactions->currentPage() === $page ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-[var(--ui-line)] bg-[var(--ui-surface-base)] text-slate-600 hover:bg-slate-100' }}">{{ $page }}</button>
                    @endfor
                    <button type="button" wire:click="nextPage" @disabled(!$transactions->hasMorePages()) class="inline-flex h-9 items-center rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 font-semibold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Halaman berikutnya"><x-ui.icon name="chevron-right" size="sm" /></button>
                </nav>
            @endif
        </div>
    </section>

    {{-- Warning modal --}}
    <div x-show="warningOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="transaction-warning-title" x-on:click.self="closeWarning" x-on:keydown.escape.window="closeWarning">
        <div class="w-full max-w-md rounded-xl bg-[var(--ui-surface-base)] p-5 shadow-2xl">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                    <x-ui.icon name="warning" size="lg" />
                </div>
                <div>
                    <p class="text-[13px] font-bold uppercase tracking-wide text-amber-700">Peringatan</p>
                    <h2 id="transaction-warning-title" class="mt-1 text-lg font-bold text-slate-900" x-text="warningMessage"></h2>
                    <p class="mt-2 text-sm text-slate-500">Gunakan tombol Ubah Data SPJ untuk mengisi deskripsi belanja sebelum membuka detail transaksi.</p>
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <x-ui.button type="button" variant="secondary" icon="check" x-on:click="closeWarning">Mengerti</x-ui.button>
            </div>
        </div>
    </div>

    {{-- Transaction editor modal --}}
    <div x-show="editorOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" x-on:click.self="closeEditor" x-on:keydown.escape.window="closeEditor">
        <form method="POST" x-bind:action="editorAction" class="w-full max-w-xl rounded-xl bg-[var(--ui-surface-base)] p-5 shadow-2xl">
            @csrf
            @method('PUT')

            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-violet-200 bg-violet-50 text-violet-700">
                        <x-ui.icon name="edit" size="lg" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[13px] font-bold uppercase tracking-[.10em] text-violet-600">Transaksi <span x-text="editor.no_bukti"></span></p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">Data SPJ Transaksi</h2>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi uraian dan kategori SPJ tanpa mengubah data asli hasil sinkronisasi.</p>
                    </div>
                </div>
                <button type="button" x-on:click="closeEditor" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--ui-line)] text-slate-500 transition hover:bg-slate-100 hover:text-slate-800" aria-label="Tutup modal">
                    <x-ui.icon name="close" size="sm" />
                </button>
            </div>

            <label class="mt-5 flex items-center gap-2 text-sm font-bold text-slate-700" for="transaction-editor-category">
                <x-ui-icon name="document" class="h-4 w-4 text-violet-600" />
                <span>Kategori SPJ</span>
            </label>
            <select id="transaction-editor-category" name="spj_category" x-model="editor.spj_category" x-ref="category" class="mt-1 w-full rounded-md border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Pilih kategori SPJ</option>
                <option value="BARANG">Barang</option>
                <option value="KONSUMSI">Konsumsi</option>
                <option value="PEMELIHARAAN">Pemeliharaan</option>
                <option value="JASA_LAINNYA">Jasa Lainnya</option>
                <option value="SPPD">SPPD</option>
                <option value="HONOR_PEGAWAI">Honor Pegawai</option>
            </select>
            @error('form.spj_category')<p class="mt-1 text-[13px] font-semibold text-rose-600">{{ $message }}</p>@enderror

            <label class="mt-4 flex items-center gap-2 text-sm font-bold text-slate-700" for="transaction-editor-description">
                <x-ui-icon name="edit" class="h-4 w-4 text-indigo-600" />
                <span>Uraian Pembayaran</span>
            </label>
            <textarea id="transaction-editor-description" name="payment_description" x-model="editor.payment_description" rows="5" class="mt-1 w-full rounded-md border border-[var(--ui-line-strong)] px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contoh: Pembelian alat tulis kantor untuk mendukung pembelajaran dan administrasi sekolah."></textarea>
            @error('form.payment_description')<p class="mt-1 text-[13px] font-semibold text-rose-600">{{ $message }}</p>@enderror

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700" for="transaction-editor-method">
                        <x-ui-icon name="transaction" class="h-4 w-4 text-emerald-600" />
                        <span>Metode Pembayaran</span>
                    </label>
                    <select id="transaction-editor-method" name="payment_method" x-model="editor.payment_method" class="mt-1 w-full rounded-md border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="transfer_bank">Transfer Bank (CMS / Non Tunai)</option>
                        <option value="siplah">SiPLah Kemdikbud</option>
                        <option value="tunai">Tunai Kas BOS</option>
                    </select>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700" for="transaction-editor-reference">
                        <x-ui-icon name="number" class="h-4 w-4 text-sky-600" />
                        <span>Referensi Bayar</span>
                    </label>
                    <input id="transaction-editor-reference" name="payment_reference" x-model="editor.payment_reference" class="mt-1 w-full rounded-md border border-[var(--ui-line-strong)] px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700" for="transaction-editor-recipient">
                        <x-ui-icon name="employee" class="h-4 w-4 text-amber-600" />
                        <span>Penerima Kuitansi</span>
                    </label>
                    <input id="transaction-editor-recipient" name="receipt_recipient_name" x-model="editor.receipt_recipient_name" class="mt-1 w-full rounded-md border border-[var(--ui-line-strong)] px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Boleh berbeda dari penerima BKU/ARKAS">
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2 border-t border-[var(--ui-line)] pt-4">
                <x-ui.button type="button" variant="secondary" icon="close" x-on:click="closeEditor">Batal</x-ui.button>
                <x-ui.button type="submit" icon="save">Simpan Data SPJ</x-ui.button>
            </div>
        </form>
    </div>
</div>
