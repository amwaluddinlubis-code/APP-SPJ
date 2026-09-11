@php($activeSpjTab = $tab ?? 'persiapan')

<div id="spj-main-tabs">
    <x-tabs :tabs="[
        ['id' => 'persiapan', 'label' => 'Persiapan', 'icon' => 'archive'],
        ['id' => 'paket', 'label' => 'Paket', 'icon' => 'document'],
        ['id' => 'laporan', 'label' => 'Laporan', 'icon' => 'report'],
        ['id' => 'monitoring', 'label' => 'Monitoring', 'icon' => 'warning'],
    ]" :activeTab="$activeSpjTab" />
</div>
