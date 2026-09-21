@php
    $accounting = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp

<div data-package-navigation hidden class="mx-5 flex flex-wrap items-center justify-between gap-2 py-4">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('spj.index', ['tab' => 'paket']) }}" title="Kembali ke semua Paket SPJ" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 transition hover:-translate-y-0.5 hover:shadow-sm">
            <span aria-hidden="true">â˜·</span><span>Semua Paket</span>
        </a>

    </div>

    <a href="{{ route('transactions.show', $transaction->id) }}" title="Buka Detail Transaksi" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 transition hover:-translate-y-0.5 hover:shadow-sm">
        <span aria-hidden="true">â†—</span><span>Lihat Transaksi</span>
    </a>
</div>

<section data-spj-package-summary data-spj-main-number="{{ $hasActiveSpjNumber ? $activeSpjDocument->document_number : '' }}" class="mx-5 mt-0 overflow-hidden rounded-xl border shadow-sm" style="border-color: var(--ui-line); background: var(--ui-surface-base); color: var(--ui-fg)">
    <div class="border-b px-4 py-5 sm:px-5" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
        <p class="text-[11px] font-bold tracking-[.16em]" style="color: var(--theme-content-accent)">PAKET DOKUMEN SPJ</p>
        <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="font-mono text-2xl font-bold sm:text-3xl" style="color: var(--ui-fg-strong)">{{ $transaction->no_bukti }}</h1>
                <p class="mt-1.5 line-clamp-2 text-base" style="color: var(--ui-fg-muted)">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian transaksi belum tersedia.' }}</p>
            </div>
            <div class="rounded-lg border px-3 py-2.5 text-left lg:text-right" style="border-color: var(--ui-line-strong); background: var(--ui-surface-base)">
                <p class="text-[11px] font-semibold" style="color: var(--ui-fg-muted)">{{ $package->status === 'CANCELLED' ? 'Nomor SPJ dibatalkan' : 'Nomor Dokumen SPJ' }}</p>
                <p class="mt-0.5 break-all font-mono text-base font-bold {{ $package->status === 'CANCELLED' ? 'line-through' : '' }}" style="color: {{ $package->status === 'CANCELLED' ? 'var(--ui-fg-muted)' : 'var(--theme-content-accent)' }}">{{ $hasActiveSpjNumber ? $activeSpjDocument->document_number : ($package->status === 'CANCELLED' ? ($cancelledSpjDocument?->document_number ?: $package->document_number) : 'Belum ditetapkan') }}</p>
            </div>
        </div>
    </div>
    <div class="grid divide-y sm:grid-cols-2 lg:grid-cols-5 lg:divide-x lg:divide-y-0" style="border-color: var(--ui-line)">
        <div class="px-3 py-2.5"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Periode</p><p class="mt-0.5 text-sm font-semibold" style="color: var(--ui-fg)">{{ $package->quarter_code }} Â· {{ $package->semester_code }}</p></div>
        <div class="px-3 py-2.5"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Penerima</p><p class="mt-0.5 truncate text-sm font-semibold" style="color: var(--ui-fg)" title="{{ $transaction->recipient_name ?: 'Belum diisi' }}">{{ $transaction->recipient_name ?: 'Belum diisi' }}</p></div>
        <div class="px-3 py-2.5 text-right"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Bruto</p><p class="mt-0.5 font-mono text-sm font-bold" style="color: var(--ui-fg)">{{ $accounting($transaction->gross_amount) }}</p></div>
        <div class="px-3 py-2.5 text-right"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Pajak</p><p class="mt-0.5 font-mono text-sm font-bold" style="color: var(--ui-fg)">{{ $accounting($transaction->tax_total) }}</p></div>
        <div class="px-3 py-2.5 text-right"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Nilai Dibayarkan</p><p class="mt-0.5 font-mono text-sm font-bold" style="color: var(--theme-content-accent)">{{ $accounting($transaction->net_amount) }}</p></div>
    </div>
</section>
