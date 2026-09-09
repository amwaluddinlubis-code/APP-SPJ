@php
    $serviceRows = $transaction->serviceRecipients->map(fn ($recipient) => [
        ...$recipient->only(['name', 'npwp', 'service_type', 'service_description', 'quantity', 'unit', 'rental_days', 'daily_rate', 'receipt_number', 'payment_reference', 'agreement_number', 'notes']),
        'usage_started_at' => $recipient->usage_started_at?->format('Y-m-d'),
        'usage_completed_at' => $recipient->usage_completed_at?->format('Y-m-d'),
        'agreement_date' => $recipient->agreement_date?->format('Y-m-d'),
    ])->values()->all();
    $serviceRows = $selectedSpjType === 'JASA_LAINNYA' ? old('service_recipients', $serviceRows) : $serviceRows;
@endphp
<fieldset data-spj-section="JASA_LAINNYA" @disabled($selectedSpjType !== 'JASA_LAINNYA') @if($selectedSpjType !== 'JASA_LAINNYA') hidden @endif class="min-w-0 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Penerima Pembayaran Jasa</h3>
    @include('spj.partials.package.categories.jasa-recipient-editor', [
        'rowLabel' => 'Penerima Jasa',
        'rows' => $serviceRows,
        'primaryNameKey' => 'name',
        'emptyRow' => ['name' => '', 'npwp' => '', 'service_type' => '', 'service_description' => '', 'quantity' => 1, 'unit' => '', 'rental_days' => 1, 'daily_rate' => 0, 'receipt_number' => '', 'payment_reference' => '', 'agreement_number' => '', 'usage_started_at' => '', 'usage_completed_at' => '', 'agreement_date' => '', 'notes' => ''],
        'fields' => [
            'name' => ['label' => 'Nama Penerima', 'required' => true],
            'npwp' => ['label' => 'NPWP'],
            'service_type' => ['label' => 'Jenis Jasa'],
            'service_description' => ['label' => 'Uraian Jasa', 'type' => 'textarea'],
            'quantity' => ['label' => 'Volume', 'type' => 'number', 'min' => 0, 'step' => '0.01'],
            'unit' => ['label' => 'Satuan'],
            'rental_days' => ['label' => 'Hari / Kali', 'format' => 'integer', 'min' => 0],
            'daily_rate' => ['label' => 'Tarif', 'format' => 'accounting'],
            'usage_started_at' => ['label' => 'Mulai', 'type' => 'date'],
            'usage_completed_at' => ['label' => 'Selesai', 'type' => 'date'],
            'receipt_number' => ['label' => 'Ref. Kuitansi'],
            'payment_reference' => ['label' => 'Ref. Pembayaran'],
            'agreement_number' => ['label' => 'No. Perjanjian'],
            'agreement_date' => ['label' => 'Tgl Perjanjian', 'type' => 'date'],
            'notes' => ['label' => 'Catatan', 'type' => 'textarea'],
        ],
    ])
</fieldset>
