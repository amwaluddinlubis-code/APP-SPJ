@php
    $travelRows = $transaction->travels->map(fn ($travel) => [
        ...$travel->only(['traveler_name', 'destination', 'purpose', 'transport_mode', 'amount', 'notes']),
        'assignment_letter_date' => $travel->assignment_letter_date?->format('Y-m-d'),
        'departure_date' => $travel->departure_date?->format('Y-m-d'),
        'return_date' => $travel->return_date?->format('Y-m-d'),
    ])->values()->all();
    $travelRows = $selectedSpjType === 'SPPD' ? old('travels', $travelRows) : $travelRows;
@endphp
<fieldset data-spj-section="SPPD" @disabled($selectedSpjType !== 'SPPD') @if($selectedSpjType !== 'SPPD') hidden @endif class="min-w-0 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Pelaksana Perjalanan Dinas</h3>
    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Satu transaksi dapat memiliki beberapa pelaksana. Nomor surat tugas diterbitkan melalui penomoran.</p>
    @include('spj.partials.package.row-editor', [
        'prefix' => 'travels', 'rowLabel' => 'Pelaksana', 'rows' => $travelRows,
        'emptyRow' => ['traveler_name' => '', 'destination' => '', 'purpose' => '', 'assignment_letter_date' => '', 'departure_date' => '', 'return_date' => '', 'transport_mode' => '', 'amount' => 0, 'notes' => ''],
        'fields' => [
            'traveler_name' => ['label' => 'Nama pelaksana', 'required' => true],
            'destination' => ['label' => 'Tujuan'], 'purpose' => ['label' => 'Maksud perjalanan', 'type' => 'textarea'],
            'assignment_letter_date' => ['label' => 'Tanggal surat tugas', 'type' => 'date'],
            'departure_date' => ['label' => 'Tanggal berangkat', 'type' => 'date'],
            'return_date' => ['label' => 'Tanggal kembali', 'type' => 'date'],
            'transport_mode' => ['label' => 'Transportasi'],
            'amount' => ['label' => 'Nilai perjalanan', 'type' => 'number', 'min' => 0, 'step' => '0.01'],
            'notes' => ['label' => 'Catatan', 'type' => 'textarea'],
        ],
    ])
</fieldset>
