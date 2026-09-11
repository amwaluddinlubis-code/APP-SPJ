@php($reviewTransaction = $package->transaction)
@php($reviewCategory = strtoupper((string) $reviewTransaction->spj_category))
@php($reviewGoods = $reviewTransaction->goods->first())
<div class="space-y-3 px-4 py-3 text-xs">
    <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Cara bayar</dt><dd class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ str_replace('_', ' ', (string) $reviewTransaction->payment_method) ?: '—' }}</dd></div>
        <div><dt class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Penerima utama</dt><dd class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewTransaction->effective_receipt_recipient_name ?: '—' }}</dd></div>
        <div><dt class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Penyedia</dt><dd class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewTransaction->vendor_name ?: '—' }}</dd></div>
        <div><dt class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Invoice</dt><dd class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewTransaction->invoice_number ?: '—' }}{{ $reviewTransaction->invoice_date ? ' · '.$reviewTransaction->invoice_date->translatedFormat('d M Y') : '' }}</dd></div>
    </dl>
    @if(in_array($reviewCategory, ['BARANG', 'KONSUMSI'], true))
        <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-3">
            <div><dt class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tgl pesanan</dt><dd class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewGoods?->order_date?->translatedFormat('d M Y') ?: '—' }}</dd></div>
            <div><dt class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tgl BAP</dt><dd class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewGoods?->bap_date?->translatedFormat('d M Y') ?: '—' }}</dd></div>
            <div><dt class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tgl BAST</dt><dd class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewGoods?->bast_date?->translatedFormat('d M Y') ?: '—' }}</dd></div>
        </dl>
    @endif
    @if($reviewCategory === 'KONSUMSI')
        <div><p class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Acara</p><p class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewTransaction->event_name ?: '—' }}{{ $reviewTransaction->event_date ? ' · '.$reviewTransaction->event_date->translatedFormat('d M Y') : '' }} ({{ $reviewTransaction->participants->count() }} peserta)</p>
        <p class="mt-1 leading-5 text-[var(--ui-fg)]">{{ $reviewTransaction->participants->pluck('name')->filter()->implode(', ') ?: 'Belum ada peserta.' }}</p></div>
    @endif
    @if($reviewCategory === 'PEMELIHARAAN')
        <div><p class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Pekerjaan</p><p class="mt-0.5 font-bold text-[var(--ui-fg-strong)]">{{ $reviewTransaction->workOrder?->work_description ?: '—' }}{{ $reviewTransaction->workOrder?->work_location ? ' · '.$reviewTransaction->workOrder->work_location : '' }}</p>
        <p class="mt-1 leading-5 text-[var(--ui-fg)]">{{ $reviewTransaction->workOrder?->workers->map(fn ($worker) => $worker->name.' ('.$worker->work_days.' hari)')->implode(', ') ?: 'Belum ada pekerja.' }}</p></div>
    @endif
    @if($reviewCategory === 'SPPD')
        <div><p class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Perjalanan dinas</p><p class="mt-1 leading-5 text-[var(--ui-fg)]">{{ $reviewTransaction->travels->map(fn ($travel) => $travel->traveler_name.' → '.$travel->destination)->implode('; ') ?: 'Belum ada pelaksana.' }}</p></div>
    @endif
    @if($reviewCategory === 'HONOR_PEGAWAI')
        <div><p class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Penerima honor</p><p class="mt-1 leading-5 text-[var(--ui-fg)]">{{ $reviewTransaction->honors->map(fn ($honor) => $honor->name)->implode(', ') ?: 'Belum ada penerima.' }}</p></div>
    @endif
    @if($reviewCategory === 'JASA_LAINNYA')
        <div><p class="font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Penerima jasa</p><p class="mt-1 leading-5 text-[var(--ui-fg)]">{{ $reviewTransaction->serviceRecipients->map(fn ($recipient) => $recipient->name)->implode(', ') ?: 'Belum ada penerima.' }}</p></div>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--ui-line)] pt-2">
        <p class="font-semibold text-[var(--ui-fg-muted)]">Bruto {{ $rupiah($reviewTransaction->gross_amount) }}</p>
        <a href="{{ route('spj.checklist', $package->id) }}" class="text-xs font-bold text-[var(--theme-content-accent)] hover:underline">Buka checklist paket →</a>
    </div>
</div>
