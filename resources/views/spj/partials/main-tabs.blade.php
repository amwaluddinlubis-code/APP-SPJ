@php($activeSpjTab = $tab ?? 'persiapan')

<div id="spj-main-tabs">
    <x-tabs :tabs="[
        ['id' => 'persiapan', 'label' => '📦 Persiapan'],
        ['id' => 'paket', 'label' => '📄 Paket'],
        ['id' => 'laporan', 'label' => '📊 Laporan'],
        ['id' => 'monitoring', 'label' => '⚠️ Monitoring'],
    ]" :activeTab="$activeSpjTab" />
</div>

@switch($activeSpjTab)
    @case('laporan')
        @include('spj.partials.laporan')
        @break

    @case('monitoring')
        @include('spj.partials.monitoring')
        @break
@endswitch
