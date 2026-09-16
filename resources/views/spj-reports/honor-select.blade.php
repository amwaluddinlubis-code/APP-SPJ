<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header title="Pilih Transaksi Pembayaran Honor" subtitle="Pilih transaksi kategori Honor Pegawai yang akan digabung dalam satu laporan penerimaan pembayaran." kicker="Laporan SPJ · Honor Pegawai">
            <x-slot:actions><x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'laporan'])">Kembali ke Laporan SPJ</x-ui.button></x-slot:actions>
        </x-page-header>
        <p class="sr-only">kategori <span class="font-bold">Honor Pegawai</span></p>
        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm"><livewire:spj-report-transaction-selector category="HONOR_PEGAWAI" /></section>
    </div>
</x-layouts.tailwind-app>
