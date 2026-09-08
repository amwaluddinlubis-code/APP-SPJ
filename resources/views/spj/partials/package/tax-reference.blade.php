@php
    $accounting = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp
<section data-spj-tax-reference class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Rincian Pajak</h3>
        <span class="text-[11px] font-medium text-[var(--ui-fg-muted)]">Readonly · sumber ARKAS/BKU</span>
    </div>
    <dl class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @foreach(['Bruto' => 'gross_amount', 'PPN' => 'ppn', 'PPh 21' => 'pph21', 'PPh 22' => 'pph22', 'PPh 23' => 'pph23', 'PPh 4(2)' => 'pph4', 'SSPD / Pajak Daerah' => 'sspd', 'Total Pajak' => 'tax_total', 'Nilai Dibayarkan' => 'net_amount'] as $label => $field)
            <div class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">{{ $label }}</dt>
                <dd class="mt-0.5 text-right font-mono text-sm font-bold text-[var(--ui-fg-strong)]">{{ $accounting($transaction->{$field}) }}</dd>
            </div>
        @endforeach
    </dl>
</section>
