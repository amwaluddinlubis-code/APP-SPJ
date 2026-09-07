@php
    $honorRows = $transaction->honors->map(fn ($honor) => [
        'name' => $honor->name, 'job_description' => $honor->position,
        'work_days' => $honor->honor_months, 'daily_rate' => $honor->rate_per_unit,
    ])->values()->all();
    $honorRows = $selectedSpjType === 'HONOR_PEGAWAI' ? old('workers', $honorRows) : $honorRows;
@endphp
<fieldset data-spj-section="HONOR_PEGAWAI" @disabled($selectedSpjType !== 'HONOR_PEGAWAI') @if($selectedSpjType !== 'HONOR_PEGAWAI') hidden @endif class="min-w-0 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Rincian Penerima Honor</h3>
    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Kelola seluruh penerima di Paket SPJ. Total bulan/kali × tarif harus sesuai bruto transaksi {{ $rupiah($transaction->gross_amount) }}.</p>
    @include('spj.partials.package.row-editor', [
        'prefix' => 'workers', 'rowLabel' => 'Penerima honor', 'rows' => $honorRows,
        'emptyRow' => ['name' => '', 'job_description' => '', 'work_days' => 1, 'daily_rate' => 0],
        'fields' => [
            'name' => ['label' => 'Nama', 'required' => true],
            'job_description' => ['label' => 'Jabatan'],
            'work_days' => ['label' => 'Bulan / kali', 'type' => 'number', 'min' => 1, 'step' => '1'],
            'daily_rate' => ['label' => 'Tarif per unit', 'type' => 'number', 'min' => 0, 'step' => '0.01'],
        ],
    ])
</fieldset>
