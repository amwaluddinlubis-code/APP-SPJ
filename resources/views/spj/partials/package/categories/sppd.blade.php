@php
    $travelRows = $transaction->travels->map(fn ($travel) => [
        ...$travel->only(['traveler_name', 'destination', 'purpose', 'transport_mode', 'amount', 'notes']),
        'assignment_letter_date' => $travel->assignment_letter_date?->format('Y-m-d'),
        'departure_date' => $travel->departure_date?->format('Y-m-d'),
        'return_date' => $travel->return_date?->format('Y-m-d'),
    ])->values()->all();
    $travelRows = $selectedSpjType === 'SPPD' ? old('travels', $travelRows) : $travelRows;
@endphp
<fieldset data-spj-section="SPPD" @disabled($selectedSpjType !== 'SPPD') @if($selectedSpjType !== 'SPPD') hidden @endif class="min-w-0 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Pelaksana Perjalanan Dinas</h3>
    @include('spj.partials.package.row-editor', [
        'prefix' => 'travels',
        'rowLabel' => 'Pelaksana',
        'rows' => $travelRows,
        'primaryNameKey' => 'traveler_name',
        'emptyRow' => ['traveler_name' => '', 'destination' => '', 'purpose' => '', 'assignment_letter_date' => '', 'departure_date' => '', 'return_date' => '', 'transport_mode' => '', 'amount' => 0, 'notes' => ''],
        'fields' => [
            'traveler_name' => ['label' => 'Nama Pelaksana', 'required' => true],
            'destination' => ['label' => 'Tujuan'],
            'purpose' => ['label' => 'Maksud Perjalanan', 'type' => 'textarea'],
            'assignment_letter_date' => ['label' => 'Tgl Surat Tugas', 'type' => 'date'],
            'departure_date' => ['label' => 'Berangkat', 'type' => 'date'],
            'return_date' => ['label' => 'Kembali', 'type' => 'date'],
            'transport_mode' => ['label' => 'Transportasi'],
            'amount' => ['label' => 'Nilai', 'format' => 'accounting'],
            'notes' => ['label' => 'Catatan', 'type' => 'textarea'],
        ],
    ])
</fieldset>
