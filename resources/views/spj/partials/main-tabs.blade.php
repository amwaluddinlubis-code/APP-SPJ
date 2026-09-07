<div id="spj-main-tabs">
    <x-tabs :tabs="[
        ['id' => 'persiapan', 'label' => '📦 Persiapan'],
        ['id' => 'paket', 'label' => '📄 Paket'],
        ['id' => 'laporan', 'label' => '📊 Laporan'],
        ['id' => 'monitoring', 'label' => '⚠️ Monitoring'],
    ]" :activeTab="$tab" />
</div>

{{-- Laporan dan Monitoring dirender berdasarkan tab server-side.
     Navigasi tab melakukan full URL navigation. x-ignore mencegah x-show legacy
     di dalam partial memproses ulang visibilitas panel aktif. --}}
@if(($tab ?? 'persiapan') === 'laporan')
    <div x-ignore data-spj-server-tab="laporan">
        @include('spj.partials.laporan')
    </div>
@elseif(($tab ?? 'persiapan') === 'monitoring')
    <div x-ignore data-spj-server-tab="monitoring">
        @include('spj.partials.monitoring')
    </div>
@endif
