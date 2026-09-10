<div class="flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('transactions.index') }}" class="ui-btn ui-btn-secondary !text-sm">← Kembali ke transaksi</a>

    <div class="flex flex-wrap items-center gap-2">
        <nav class="flex items-center gap-1" aria-label="Navigasi transaksi">
            @if ($previousTransaction)
                <a href="{{ route('transactions.show', $previousTransaction->id) }}"
                    title="Transaksi sebelumnya: {{ $previousTransaction->no_bukti ?: 'Tanpa nomor bukti' }}"
                    aria-label="Transaksi sebelumnya: {{ $previousTransaction->no_bukti ?: 'Tanpa nomor bukti' }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] text-[var(--ui-fg-muted)] transition hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-fg-strong)]">
                    <x-ui-icon name="chevron-left" class="h-4 w-4" />
                </a>
            @else
                <span title="Tidak ada transaksi sebelumnya" aria-label="Tidak ada transaksi sebelumnya" class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)] opacity-50">
                    <x-ui-icon name="chevron-left" class="h-4 w-4" />
                </span>
            @endif

            @if ($nextTransaction)
                <a href="{{ route('transactions.show', $nextTransaction->id) }}"
                    title="Transaksi selanjutnya: {{ $nextTransaction->no_bukti ?: 'Tanpa nomor bukti' }}"
                    aria-label="Transaksi selanjutnya: {{ $nextTransaction->no_bukti ?: 'Tanpa nomor bukti' }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] text-[var(--ui-fg-muted)] transition hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-fg-strong)]">
                    <x-ui-icon name="chevron-right" class="h-4 w-4" />
                </a>
            @else
                <span title="Tidak ada transaksi selanjutnya" aria-label="Tidak ada transaksi selanjutnya" class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)] opacity-50">
                    <x-ui-icon name="chevron-right" class="h-4 w-4" />
                </span>
            @endif
        </nav>

        <x-ui.status-badge :status="$transaction->status" />

        @if ($sourceStatus === 'SOURCE_MISSING')
            <x-ui.status-badge status="SOURCE_MISSING" label="Tidak muncul di sync terakhir" />
        @else
            <x-ui.status-badge status="ACTIVE" label="Data ARKAS aktif" />
        @endif

        @if ($transaction->requires_reconciliation)
            <x-ui.status-badge status="REQUIRES_RECONCILIATION" />
        @endif

        @if ($transaction->spjPackage)
            <x-ui.status-badge :status="$transaction->spjPackage->status" :label="'SPJ · '.$transaction->spjPackage->status" />
            <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}"
                class="ui-btn ui-btn-primary !min-h-0 !px-3 !py-1.5 !text-xs">
                Buka Paket SPJ
            </a>
        @elseif($transaction->items->isNotEmpty())
            <a href="{{ route('transactions.prepare-spj', $transaction->id) }}"
                class="ui-btn ui-btn-primary !min-h-0 !px-3 !py-1.5 !text-xs">
                Siapkan Paket SPJ
            </a>
        @endif
    </div>
</div>

@if ($needsAttention)
    <x-ui.alert type="warning" title="Transaksi ini perlu perhatian sebelum dokumen difinalkan.">
        <p>
            @if ($sourceStatus === 'SOURCE_MISSING')
                Data ARKAS transaksi ini tidak muncul pada sinkronisasi terakhir. Data manual tetap dipertahankan.
            @endif
            @if ($transaction->requires_reconciliation)
                Ada perubahan sumber ARKAS yang perlu ditinjau agar dokumen tidak berubah diam-diam.
            @endif
        </p>
    </x-ui.alert>
@endif

<x-page-header :title="$transaction->no_bukti"
    :subtitle="$transaction->description ?: 'Uraian transaksi belum tersedia.'"
    kicker="{{ $headerVisual['label'] }} · Detail transaksi ARKAS / BKU">
    <div class="grid sm:grid-cols-3">
        <x-stat-item label="Nilai bruto" :value="$rupiah($transaction->gross_amount)"
            :hint="$transaction->transaction_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia'" />
        <x-stat-item label="Nilai netto" :value="$rupiah($transaction->net_amount)"
            hint="Nilai setelah potongan pajak" />
        <x-stat-item label="Rincian barang/jasa" value="{{ $transaction->items->count() }} item"
            :hint="'Akumulasi: '.$rupiah($totalItems)" />
    </div>
</x-page-header>

<section>
    <x-ui.panel variant="soft">
        <x-slot:title>
            <div class="flex items-center gap-2">
                <span>Informasi Referensi ARKAS / BKU</span>
                <span class="rounded bg-[var(--ui-line)] px-1.5 py-0.5 text-[11px] font-semibold text-[var(--ui-fg-muted)]">Readonly</span>
            </div>
        </x-slot:title>

        <div class="grid divide-y divide-[var(--ui-line)] md:grid-cols-2 md:divide-x md:divide-y-0 xl:grid-cols-4">
            <div class="px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Penerima / Penyedia</p>
                <p class="mt-1 font-semibold text-[var(--ui-fg-strong)]">{{ $transaction->recipient_name ?: 'Belum diisi' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kode Kegiatan</p>
                <p class="mt-1 font-mono text-base font-semibold text-[var(--theme-content-accent)]">{{ $transaction->activity_code ?: '—' }}</p>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->activity_name ?: 'Kegiatan belum tersedia' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kode Rekening</p>
                <p class="mt-1 font-mono text-base font-semibold text-[var(--theme-content-accent)]">{{ $transaction->account_code ?: '—' }}</p>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->account_name ?: 'Rekening belum tersedia' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Pajak</p>
                <p class="mt-1 font-mono text-base font-bold text-[var(--ui-fg-strong)]">{{ $rupiah($transaction->tax_total) }}</p>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Total pajak sesuai sumber BKU/ARKAS.</p>
            </div>
        </div>
    </x-ui.panel>
</section>
