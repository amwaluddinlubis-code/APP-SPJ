<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <p class="text-xs font-bold tracking-[.16em] text-amber-100">HASIL SINKRONISASI ARKAS</p>
                <h1 class="mt-2 text-2xl font-bold">Pajak</h1>
                <p class="mt-1 text-base text-amber-100">Rekap pajak dari transaksi BKU pada konteks tahun dan sumber dana aktif.</p>
            </div>
            <div class="border-b border-slate-100 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400">Total Tahunan {{ $year->year }}</div><div class="grid divide-y divide-slate-100 sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Transaksi Pajak</p><p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($summary->count, 0, ',', '.') }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">PPN</p><p class="mt-1 text-xl font-bold text-indigo-700">{{ $rupiah($summary->ppn) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">PPh</p><p class="mt-1 text-xl font-bold text-rose-700">{{ $rupiah($summary->pph21 + $summary->pph22 + $summary->pph23 + $summary->pph4) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Total Pajak</p><p class="mt-1 text-xl font-bold text-amber-700">{{ $rupiah($summary->total) }}</p></div>
            </div>
        </section>
        <x-page-filter :month="$month" :quarter="$quarter" :semester="$semester" :search="$search">
            <div class="border-b border-indigo-100 px-5 py-3 text-xs font-bold uppercase tracking-wide text-indigo-700">Subtotal Periode Terpilih</div><div class="grid divide-y divide-indigo-100 sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-indigo-500">Transaksi</p><p class="mt-1 text-xl font-bold text-indigo-900">{{ number_format($filteredSummary->count, 0, ',', '.') }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-indigo-500">PPN</p><p class="mt-1 text-xl font-bold text-indigo-900">{{ $rupiah($filteredSummary->ppn) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-indigo-500">PPh</p><p class="mt-1 text-xl font-bold text-indigo-900">{{ $rupiah($filteredSummary->pph21 + $filteredSummary->pph22 + $filteredSummary->pph23 + $filteredSummary->pph4) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-indigo-500">Total Pajak</p><p class="mt-1 text-xl font-bold text-indigo-900">{{ $rupiah($filteredSummary->total) }}</p></div>
            </div>
        </x-page-filter>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <x-breadcrumb :items="[['label' => 'Pajak Sinkronisasi']]" />
                <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-bold text-slate-800">Daftar Pajak Tersinkron</h2><p class="mt-1 text-sm text-slate-500">Satu nomor bukti ditampilkan satu kali.</p></div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input name="q" value="{{ $search }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Cari nomor bukti atau penerima">
                    <select name="month" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Semua bulan</option>
                        @foreach([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $number => $name)
                            <option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @if($search !== '' || $month)<a href="{{ route('taxes.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-semibold text-slate-600">Reset</a>@endif
                </div>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Bukti / Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Penerima</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPN</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 21</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 22</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 23</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 4 / SSPD</th>
                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Total</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($transactions as $transaction)
                            <tr class="transition hover:bg-amber-50/50">
                                <td class="px-5 py-4"><a href="{{ route('transactions.show', $transaction) }}" class="font-mono font-bold text-indigo-700">{{ $transaction->no_bukti }}</a><p class="mt-1 text-xs text-slate-500">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?? '-' }}</p></td>
                                <td class="max-w-xs px-4 py-4"><p class="truncate font-semibold text-slate-800">{{ $transaction->recipient_name ?: 'Penerima belum diisi' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $transaction->description ?: 'Tanpa uraian' }}</p></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->ppn) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph21) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph22) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph23) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph4 + $transaction->sspd) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-bold text-amber-700">{{ $rupiah($transaction->tax_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">Belum ada pajak tersinkron.</p><p class="mt-1 text-sm text-slate-500">Data akan muncul setelah sinkronisasi BKU yang memiliki pajak.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/30 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <x-page-table-per-page :total="$transactions->total()" />
                <div>{{ $transactions->links() }}</div>
            </div>
        </section>
    </div>
</x-layouts.tailwind-app>
