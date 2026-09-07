<div class="spj-work-summary grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
    <button type="button" data-tab="persiapan"
        class="group bg-[var(--ui-surface-base)] px-5 py-4 text-left transition hover:bg-slate-50 sm:px-6">
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Transaksi siap</p><span
                class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500">→</span>
        </div>
        <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($readyTransactions ?? 0, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-slate-500">Siap diproses menjadi paket</p>
    </button>
    <button type="button" data-tab="paket"
        class="group bg-[var(--ui-surface-base)] px-5 py-4 text-left transition hover:bg-slate-50 sm:px-6">
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Menunggu nomor</p><span
                class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500">→</span>
        </div>
        <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($packagesAwaitingNumber, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-slate-500">Paket yang belum bernomor</p>
    </button>
    <button type="button" data-tab="persiapan"
        class="group bg-[var(--ui-surface-base)] px-5 py-4 text-left transition hover:bg-slate-50 sm:px-6">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Belum dibuat paket</p>
        <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($transactionsWithoutPackage, 0, ',', '.') }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Transaksi yang perlu diproses</p>
    </button>
    <div class="bg-[var(--ui-surface-base)] px-5 py-4 sm:px-6">
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sudah bernomor</p><span
                class="text-xs font-bold text-emerald-700">{{ $spjProgress }}% paket</span>
        </div>
        <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($numberedPackages ?? 0, 0, ',', '.') }}</p>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[var(--ui-surface-muted)]">
            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $spjProgress }}%"></div>
        </div>
    </div>
</div>
