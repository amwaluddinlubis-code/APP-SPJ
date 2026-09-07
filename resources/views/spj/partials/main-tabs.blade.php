<div id="spj-main-tabs">
    <x-tabs :tabs="[
        ['id' => 'persiapan', 'label' => '📦 Persiapan'],
        ['id' => 'paket', 'label' => '📄 Paket'],
        ['id' => 'laporan', 'label' => '📊 Laporan'],
        ['id' => 'monitoring', 'label' => '⚠️ Monitoring'],
    ]" :activeTab="$tab" />
</div>

{{-- Report and monitoring live outside the legacy Paket wrapper so each tab remains independently visible. --}}
@include('spj.partials.laporan')
@include('spj.partials.monitoring')
