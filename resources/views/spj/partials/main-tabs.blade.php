<div id="spj-main-tabs">
    <x-tabs :tabs="[
        ['id' => 'persiapan', 'label' => '📦 Persiapan'],
        ['id' => 'paket', 'label' => '📄 Paket'],
        ['id' => 'laporan', 'label' => '📊 Laporan'],
        ['id' => 'monitoring', 'label' => '⚠️ Monitoring'],
    ]" :activeTab="$tab" />
</div>
