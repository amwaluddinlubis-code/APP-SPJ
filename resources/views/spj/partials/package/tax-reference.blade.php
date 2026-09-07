<section data-spj-tax-reference class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Referensi Pajak Transaksi</h3>
    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Readonly dari ARKAS/BKU. Paket SPJ tidak menghitung ulang atau mengubah nilai transaksi dan pajak.</p>
    <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach(['Bruto' => 'gross_amount', 'PPN' => 'ppn', 'PPh 21' => 'pph21', 'PPh 22' => 'pph22', 'PPh 23' => 'pph23', 'PPh 4(2)' => 'pph4', 'SSPD / Pajak Daerah' => 'sspd', 'Total Pajak' => 'tax_total', 'Nilai Netto' => 'net_amount'] as $label => $field)
            <div><dt class="text-xs text-[var(--ui-fg-muted)]">{{ $label }}</dt><dd class="mt-1 font-semibold text-[var(--ui-fg-strong)]">{{ $rupiah($transaction->{$field}) }}</dd></div>
        @endforeach
    </dl>
</section>
