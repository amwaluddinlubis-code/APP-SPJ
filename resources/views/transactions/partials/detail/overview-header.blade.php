<div class="flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('transactions.index') }}" class="ui-btn ui-btn-secondary !text-sm">← Kembali ke
        transaksi</a>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.status-badge :status="$transaction->status" />
        @if ($sourceStatus === 'SOURCE_MISSING')
            <x-ui.status-badge status="SOURCE_MISSING" label="Tidak muncul di sync terakhir" />
        @else
            <x-ui.status-badge status="ACTIVE" label="Data ARKAS aktif" />
        @endif
        @if ($transaction->requires_reconciliation)
            <x-ui.status-badge status="REQUIRES_RECONCILIATION" />
        @endif
        @if ($transaction->spj_category)
            <x-ui.badge variant="theme">SPJ: {{ $spjTypeLabel($transaction->spj_category) }}</x-ui.badge>
        @endif
        @if ($isSiplah)
            <x-ui.badge variant="theme">Pembelian SiPLah</x-ui.badge>
        @endif
        @if ($transaction->spjPackage?->document_number)
            <x-ui.status-badge status="NUMBERED" :label="$transaction->spjPackage->document_number" />
        @elseif($transaction->items->isNotEmpty())
            <a href="#modul-buat-spj" class="ui-btn ui-btn-primary !min-h-0 !py-1.5 !px-3 !text-xs">Buat SPJ</a>
        @endif
    </div>
</div>

@if ($needsAttention)
    <x-ui.alert type="warning" title="Transaksi ini perlu perhatian sebelum dokumen difinalkan.">
        <p>
            @if ($sourceStatus === 'SOURCE_MISSING')
                Data ARKAS transaksi ini tidak muncul pada sinkronisasi terakhir. Data manual tetap
                dipertahankan.
            @endif
            @if ($transaction->requires_reconciliation)
                Ada perubahan sumber ARKAS yang perlu ditinjau agar dokumen tidak berubah diam-diam.
            @endif
        </p>
    </x-ui.alert>
@endif

<x-page-header :title="$transaction->no_bukti" :subtitle="$transaction->payment_description ?: $transaction->description ?: 'Uraian transaksi belum tersedia.'"
    kicker="{{ $headerVisual['label'] }} · Detail transaksi / paket SPJ">
    <div class="grid sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-item label="Nilai bruto" :value="$rupiah($transaction->gross_amount)" :hint="$transaction->transaction_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia'" />
        <x-stat-item label="Total pajak" :value="$rupiah($transaction->tax_total)" hint="PPN, PPh, dan pajak daerah" />
        <x-stat-item label="Nilai dibayarkan" :value="$rupiah($transaction->net_amount)" :hint="['transfer_bank' => 'Transfer Bank', 'siplah' => 'SiPLah', 'tunai' => 'Tunai'][
            $paymentMethod
        ] ?? 'Cara bayar belum diisi'" />
        <x-stat-item label="Rincian barang/jasa" value="{{ $transaction->items->count() }} item"
            :hint="'Akumulasi: ' . $rupiah($totalItems)" />
    </div>
</x-page-header>

<section>
    <x-ui.panel variant="soft">
        <x-slot:title>
            <div class="flex items-center gap-2">
                <span>Informasi Referensi ARKAS / BKU</span>
                <span
                    class="rounded bg-[var(--ui-line)] px-1.5 py-0.5 text-[11px] font-semibold text-[var(--ui-fg-muted)]">Readonly</span>
            </div>
        </x-slot:title>
        <div class="grid divide-y divide-[var(--ui-line)] md:grid-cols-3 md:divide-x md:divide-y-0">
            <div class="px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Penerima /
                    Penyedia</p>
                <p class="mt-1 font-semibold text-[var(--ui-fg-strong)]">
                    {{ $transaction->recipient_name ?: 'Belum diisi' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kode Kegiatan</p>
                <p class="mt-1 font-mono text-base font-semibold text-[var(--theme-content-accent)]">
                    {{ $transaction->activity_code ?: '—' }}</p>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">
                    {{ $transaction->activity_name ?: 'Kegiatan belum tersedia' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kode Rekening</p>
                <p class="mt-1 font-mono text-base font-semibold text-[var(--theme-content-accent)]">
                    {{ $transaction->account_code ?: '—' }}</p>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">
                    {{ $transaction->account_name ?: 'Rekening belum tersedia' }}</p>
            </div>
        </div>
    </x-ui.panel>
</section>
