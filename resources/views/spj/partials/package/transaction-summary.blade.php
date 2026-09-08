@php
    $accounting = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp

<div data-package-navigation hidden class="mx-5 flex flex-wrap items-center justify-between gap-2 py-4">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('spj.index', ['tab' => 'paket']) }}" title="Kembali ke semua Paket SPJ" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 transition hover:-translate-y-0.5 hover:shadow-sm">
            <span aria-hidden="true">☷</span><span>Semua Paket</span>
        </a>

        @if($previousPackageId ?? null)
            <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $previousPackageId]) }}" title="Buka Paket SPJ sebelumnya pada tahun anggaran dan sumber dana aktif" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                <span aria-hidden="true">←</span><span>Paket Sebelumnya</span>
            </a>
        @else
            <button type="button" data-package-nav-missing="previous" aria-disabled="true" title="Tidak ada Paket SPJ sebelumnya pada tahun anggaran dan sumber dana aktif" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 opacity-55 transition hover:opacity-80">
                <span aria-hidden="true">←</span><span>Paket Sebelumnya</span>
            </button>
        @endif

        @if($nextPackageId ?? null)
            <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $nextPackageId]) }}" title="Buka Paket SPJ setelahnya pada tahun anggaran dan sumber dana aktif" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                <span>Paket Setelahnya</span><span aria-hidden="true">→</span>
            </a>
        @else
            <button type="button" data-package-nav-missing="next" aria-disabled="true" title="Tidak ada Paket SPJ setelahnya pada tahun anggaran dan sumber dana aktif" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 opacity-55 transition hover:opacity-80">
                <span>Paket Setelahnya</span><span aria-hidden="true">→</span>
            </button>
        @endif
    </div>

    <a href="{{ route('transactions.show', $transaction->id) }}" title="Buka Detail Transaksi" class="ui-btn ui-btn-secondary inline-flex items-center gap-2 transition hover:-translate-y-0.5 hover:shadow-sm">
        <span aria-hidden="true">↗</span><span>Lihat Transaksi</span>
    </a>
</div>

<section data-spj-package-summary data-spj-main-number="{{ $hasActiveSpjNumber ? $activeSpjDocument->document_number : '' }}" class="mx-5 mt-0 overflow-hidden rounded-xl border shadow" style="border-color: var(--ui-line); background: var(--ui-surface-base); color: var(--ui-fg)">
    <div class="px-4 py-5 sm:px-5" style="background: linear-gradient(115deg, var(--theme-sidebar-deep), var(--theme-sidebar)); color: var(--text-comfort-on-dark)">
        <p class="text-[11px] font-bold tracking-[.16em]" style="color: var(--text-comfort-on-dark-muted)">PAKET DOKUMEN SPJ</p>
        <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="font-mono text-2xl font-bold sm:text-3xl" style="color: var(--text-comfort-on-dark) !important">{{ $transaction->no_bukti }}</h1>
                <p class="mt-1.5 line-clamp-2 text-base" style="color: var(--text-comfort-on-dark-muted)">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian transaksi belum tersedia.' }}</p>
            </div>
            <div class="rounded-lg px-3 py-2.5 text-left ring-1 lg:text-right" style="background: color-mix(in srgb, var(--ui-surface-base) 10%, transparent); --tw-ring-color: color-mix(in srgb, var(--text-comfort-on-dark) 24%, transparent)">
                <p class="text-[11px] font-semibold {{ $package->status === 'CANCELLED' ? 'text-rose-200' : '' }}" @if($package->status !== 'CANCELLED') style="color: var(--text-comfort-on-dark-muted)" @endif>{{ $package->status === 'CANCELLED' ? 'Nomor SPJ dibatalkan' : 'Nomor Dokumen SPJ' }}</p>
                <p class="mt-0.5 break-all font-mono text-base font-bold {{ $package->status === 'CANCELLED' ? 'text-rose-100 line-through' : '' }}" @if($package->status !== 'CANCELLED') style="color: var(--text-comfort-on-dark) !important" @endif>{{ $hasActiveSpjNumber ? $activeSpjDocument->document_number : ($package->status === 'CANCELLED' ? ($cancelledSpjDocument?->document_number ?: $package->document_number) : 'Belum ditetapkan') }}</p>
            </div>
        </div>
    </div>
    <div class="grid divide-y sm:grid-cols-2 lg:grid-cols-5 lg:divide-x lg:divide-y-0" style="border-color: var(--ui-line)">
        <div class="px-3 py-2.5"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Periode</p><p class="mt-0.5 text-sm font-semibold" style="color: var(--ui-fg)">{{ $package->quarter_code }} · {{ $package->semester_code }}</p></div>
        <div class="px-3 py-2.5"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Penerima</p><p class="mt-0.5 truncate text-sm font-semibold" style="color: var(--ui-fg)" title="{{ $transaction->recipient_name ?: 'Belum diisi' }}">{{ $transaction->recipient_name ?: 'Belum diisi' }}</p></div>
        <div class="px-3 py-2.5 text-right"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Bruto</p><p class="mt-0.5 font-mono text-sm font-bold" style="color: var(--ui-fg)">{{ $accounting($transaction->gross_amount) }}</p></div>
        <div class="px-3 py-2.5 text-right"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Pajak</p><p class="mt-0.5 font-mono text-sm font-bold text-amber-700">{{ $accounting($transaction->tax_total) }}</p></div>
        <div class="px-3 py-2.5 text-right"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Nilai Dibayarkan</p><p class="mt-0.5 font-mono text-sm font-bold text-emerald-700">{{ $accounting($transaction->net_amount) }}</p></div>
    </div>
</section>
