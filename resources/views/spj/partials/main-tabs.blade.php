<div id="spj-main-tabs">
    <x-tabs :tabs="[
        ['id' => 'persiapan', 'label' => '📦 Persiapan'],
        ['id' => 'paket', 'label' => '📄 Paket'],
        ['id' => 'laporan', 'label' => '📊 Laporan'],
        ['id' => 'monitoring', 'label' => '⚠️ Monitoring'],
    ]" :activeTab="$tab" />
</div>

{{-- Laporan dan Monitoring dirender berdasarkan tab server-side.
     Navigasi tab melakukan full URL navigation, jadi kedua panel tidak perlu
     bergantung pada x-show atau struktur DOM Paket yang masih legacy. --}}
@if(($tab ?? 'persiapan') === 'laporan')
    @include('spj.partials.laporan')
@elseif(($tab ?? 'persiapan') === 'monitoring')
    @include('spj.partials.monitoring')
@endif
