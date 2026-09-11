<div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
    <x-stat-item label="Transaksi siap" :value="number_format($readyTransactions ?? 0, 0, ',', '.')" hint="Siap diproses menjadi paket" />
    <x-stat-item label="Menunggu nomor" :value="number_format($packagesAwaitingNumber, 0, ',', '.')" hint="Paket yang belum bernomor" value-class="text-amber-700" />
    <x-stat-item label="Belum dibuat paket" :value="number_format($transactionsWithoutPackage, 0, ',', '.')" hint="Transaksi yang perlu diproses" />
    <x-stat-item label="Sudah bernomor" :value="number_format($numberedPackages ?? 0, 0, ',', '.')" :hint="$spjProgress.'% paket'" value-class="text-emerald-700" />
</div>
