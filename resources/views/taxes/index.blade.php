<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))

    <div class="space-y-6">
        <x-page-header
            title="Pajak"
            subtitle="Rekap pajak dari transaksi BKU pada konteks tahun dan sumber dana aktif."
            kicker="Hasil Sinkronisasi ARKAS"
        >
            <div class="border-b border-slate-100 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400 sm:px-6">Total Tahunan {{ $year->year }}</div>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Transaksi Pajak" :value="number_format($summary->count, 0, ',', '.')" hint="Transaksi mengandung pajak" />
                <x-stat-item label="PPN" :value="$rupiah($summary->ppn)" hint="Total PPN tahunan" value-class="text-indigo-700" />
                <x-stat-item label="PPh" :value="$rupiah($summary->pph21 + $summary->pph22 + $summary->pph23 + $summary->pph4)" hint="Gabungan PPh" value-class="text-rose-700" />
                <x-stat-item label="Total Pajak" :value="$rupiah($summary->total)" hint="Total seluruh pajak" value-class="text-amber-700" />
            </div>
        </x-page-header>

        <x-page-filter :month="$month" :quarter="$quarter" :semester="$semester" :search="$search">
            <div class="border-b border-indigo-100 px-5 py-3 text-xs font-bold uppercase tracking-wide text-indigo-700">Subtotal Periode Terpilih</div>
            <div class="grid divide-y divide-indigo-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Transaksi" :value="number_format($filteredSummary->count, 0, ',', '.')" value-class="text-indigo-900" />
                <x-stat-item label="PPN" :value="$rupiah($filteredSummary->ppn)" value-class="text-indigo-900" />
                <x-stat-item label="PPh" :value="$rupiah($filteredSummary->pph21 + $filteredSummary->pph22 + $filteredSummary->pph23 + $filteredSummary->pph4)" value-class="text-indigo-900" />
                <x-stat-item label="Total Pajak" :value="$rupiah($filteredSummary->total)" value-class="text-indigo-900" />
            </div>
        </x-page-filter>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <x-ui.toolbar class="border-b border-[var(--ui-line)] px-5 py-3 sm:px-6">
                <div>
                    <h2 class="font-bold" style="color: var(--ui-fg)">Daftar Pajak Tersinkron</h2>
                    <p class="mt-0.5 text-sm" style="color: var(--ui-fg-muted)">Satu nomor bukti ditampilkan satu kali.</p>
                </div>
                <x-slot:actions>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <input type="hidden" name="quarter" value="{{ $quarter }}">
                        <input type="hidden" name="semester" value="{{ $semester }}">
                        <input type="hidden" name="perPage" value="{{ request('perPage', 15) }}">
                        <x-ui.search-group name="q" :value="$search" placeholder="Cari bukti atau penerima" width="w-72" />
                    </form>
                    <x-page-table-per-page :total="$transactions->total()" name="perPage" :current="request('perPage', 15)" />
                </x-slot:actions>
            </x-ui.toolbar>

            <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-[var(--ui-line)] px-5 py-3" style="background: var(--ui-bg-subtle)">
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="quarter" value="{{ $quarter }}">
                <input type="hidden" name="semester" value="{{ $semester }}">
                <input type="hidden" name="perPage" value="{{ request('perPage', 15) }}">
                <label for="tax-month" class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Bulan</label>
                <x-ui.select id="tax-month" name="month" onchange="this.form.submit()" class="!w-auto">
                    <option value="">Semua bulan</option>
                    @foreach([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $number => $name)
                        <option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>
                    @endforeach
                </x-ui.select>
                @if($search !== '' || $month || $quarter || $semester)
                    <x-ui.button variant="secondary" :href="route('taxes.index')">Reset</x-ui.button>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table data-pagination="server" class="min-w-full divide-y divide-slate-200 text-sm">
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

            <x-ui.server-pagination :paginator="$transactions" noun="transaksi" />
        </section>
    </div>
</x-layouts.tailwind-app>