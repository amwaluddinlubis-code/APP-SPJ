<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header
            title="Saran Perencanaan RKAS"
            subtitle="Baseline pagu awal dari tahun lalu dan panduan penyerapan sisa pagu berjalan untuk bahan musyawarah."
            kicker="Anggaran & Realisasi"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('rkas-planning.export', 'pagu-awal')" icon="download">Unduh Saran Pagu</x-ui.button>
                <x-ui.button variant="secondary" :href="route('rkas-planning.export', 'sisa-pagu')" icon="download">Unduh Saran Sisa</x-ui.button>
            </x-slot:actions>
        </x-page-header>

        <livewire:rkas-planning-suggestion-tables />
    </div>
</x-layouts.tailwind-app>
