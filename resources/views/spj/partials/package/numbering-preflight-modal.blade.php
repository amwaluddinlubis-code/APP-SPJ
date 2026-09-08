@php
    $preflightOrderDate = $purchaseDetails?->order_date ?: $transaction->order_date;
    $preflightBapDate = $purchaseDetails?->bap_date ?: $transaction->bap_date;
    $preflightBastDate = $purchaseDetails?->bast_date ?: $transaction->bast_date;
@endphp

<div x-data="{ open: false }" class="mt-4">
    <button type="button" @click="open = true" class="rounded-md bg-violet-600 px-4 py-2 text-base font-bold text-white shadow hover:bg-violet-700">
        Periksa &amp; Terbitkan nomor SPJ
    </button>
    <p class="mt-2 text-xs text-slate-500">Sistem menampilkan data sumber dan Data Umum terakhir sebelum nomor diterbitkan.</p>

    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-4" @keydown.escape.window="open = false">
        <div x-show="open" x-transition @click.outside="open = false" class="w-full max-w-3xl overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-[var(--ui-line)] px-5 py-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Verifikasi sebelum penomoran</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Pastikan data dokumen sudah benar</h3>
                    <p class="mt-1 text-sm text-slate-500">Setelah dikonfirmasi, nomor mengikuti format aktif dan tanggal peristiwa dokumen.</p>
                </div>
                <button type="button" @click="open = false" class="rounded-md px-2 py-1 text-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">×</button>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    'TGL_RKAS' => $transaction->rkas_date?->format('d-m-Y'),
                    'TGL_TRANSAKSI' => $transaction->transaction_date?->format('d-m-Y'),
                    'NO_BUKTI' => $transaction->no_bukti,
                    'BRUTO' => $rupiah($transaction->gross_amount),
                    'TGL_PESANAN' => $preflightOrderDate?->format('d-m-Y'),
                    'TGL_BAP' => $preflightBapDate?->format('d-m-Y'),
                    'TGL_BAST' => $preflightBastDate?->format('d-m-Y'),
                ] as $label => $value)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                        <p class="mt-1 break-words text-sm font-bold text-slate-900">{{ filled($value) ? $value : '—' }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:col-span-2 lg:col-span-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">PAYMENT_DESCRIPTION</p>
                    <p class="mt-1 whitespace-pre-line text-sm font-semibold text-slate-900">{{ filled($transaction->payment_description) ? $transaction->payment_description : '— BELUM DIISI —' }}</p>
                </div>
            </div>

            <div class="border-t border-[var(--ui-line)] bg-slate-50 px-5 py-4">
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Penomoran tetap divalidasi oleh server. Data Umum wajib lengkap dan Bulan Pesanan tidak boleh lebih awal dari Bulan RKAS.
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Kembali periksa</button>
                    <form method="POST" action="{{ route('spj.assign-number', $package->id) }}">
                        @csrf
                        <button class="rounded-md bg-violet-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-violet-700">Konfirmasi &amp; Terbitkan Nomor</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
