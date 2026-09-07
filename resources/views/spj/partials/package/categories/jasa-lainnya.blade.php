@php
    $serviceRows = $transaction->serviceRecipients->map(fn ($recipient) => [
        ...$recipient->only(['name', 'npwp', 'service_type', 'service_description', 'quantity', 'unit', 'rental_days', 'daily_rate', 'receipt_number', 'payment_reference', 'agreement_number', 'notes']),
        'usage_started_at' => $recipient->usage_started_at?->format('Y-m-d'),
        'usage_completed_at' => $recipient->usage_completed_at?->format('Y-m-d'),
        'agreement_date' => $recipient->agreement_date?->format('Y-m-d'),
    ])->values()->all();
    $serviceRows = $selectedSpjType === 'JASA_LAINNYA' ? old('service_recipients', $serviceRows) : $serviceRows;
@endphp
<fieldset data-spj-section="JASA_LAINNYA" @disabled($selectedSpjType !== 'JASA_LAINNYA') @if($selectedSpjType !== 'JASA_LAINNYA') hidden @endif class="min-w-0 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Penerima Pembayaran Jasa</h3>
    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Jumlah volume × hari × tarif seluruh penerima harus sesuai bruto {{ $rupiah($transaction->gross_amount) }}. Pajak dan netto mengikuti rekonsiliasi transaksi.</p>
    @include('spj.partials.package.row-editor', [
        'prefix' => 'service_recipients', 'rowLabel' => 'Penerima jasa', 'rows' => $serviceRows,
        'emptyRow' => ['name' => '', 'npwp' => '', 'service_type' => '', 'service_description' => '', 'quantity' => 1, 'unit' => '', 'rental_days' => 1, 'daily_rate' => 0, 'receipt_number' => '', 'payment_reference' => '', 'agreement_number' => '', 'usage_started_at' => '', 'usage_completed_at' => '', 'agreement_date' => '', 'notes' => ''],
        'fields' => [
            'name' => ['label' => 'Nama penerima', 'required' => true], 'npwp' => ['label' => 'NPWP'],
            'service_type' => ['label' => 'Jenis jasa'], 'service_description' => ['label' => 'Uraian jasa', 'type' => 'textarea'],
            'quantity' => ['label' => 'Volume jasa', 'type' => 'number', 'min' => 0, 'step' => '0.01'],
            'unit' => ['label' => 'Satuan jasa'], 'rental_days' => ['label' => 'Hari / kali', 'type' => 'number', 'min' => 0, 'step' => '0.01'],
            'daily_rate' => ['label' => 'Tarif jasa', 'type' => 'number', 'min' => 0, 'step' => '0.01'],
            'usage_started_at' => ['label' => 'Mulai penggunaan', 'type' => 'date'],
            'usage_completed_at' => ['label' => 'Selesai penggunaan', 'type' => 'date'],
            'receipt_number' => ['label' => 'Referensi kuitansi'], 'payment_reference' => ['label' => 'Referensi pembayaran'],
            'agreement_number' => ['label' => 'Nomor perjanjian'], 'agreement_date' => ['label' => 'Tanggal perjanjian', 'type' => 'date'],
            'notes' => ['label' => 'Catatan', 'type' => 'textarea'],
        ],
    ])
</fieldset>
