<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))

    <div class="space-y-6">
        <x-page-header
            title="Rekonsiliasi Data"
            kicker="Perubahan sumber ARKAS"
            subtitle="Tinjau transaksi yang berubah atau tidak lagi muncul pada sinkronisasi sebelum melanjutkan dokumen SPJ."
        >
            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-item label="Perlu perhatian" :value="number_format($summary['total'], 0, ',', '.')" hint="Semua transaksi yang perlu ditinjau" />
                <x-stat-item label="Data berubah" :value="number_format($summary['changed'], 0, ',', '.')" hint="Sumber ARKAS berubah setelah data SPJ tersedia" value-class="text-orange-700" />
                <x-stat-item label="Tidak muncul" :value="number_format($summary['missing'], 0, ',', '.')" hint="Tidak ditemukan pada sinkronisasi terakhir" value-class="text-rose-700" />
                <x-stat-item label="Sudah punya paket" :value="number_format($summary['with_package'], 0, ',', '.')" hint="Perlu kehati-hatian sebelum finalisasi" value-class="text-indigo-700" />
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 font-bold text-amber-700">!</div>
                <div>
                    <h2 class="font-bold text-amber-950">Rekonsiliasi tidak menghapus data SPJ operator</h2>
                    <p class="mt-1 text-sm leading-6 text-amber-800">Halaman ini hanya membantu meninjau transaksi yang perlu perhatian. Data manual, paket SPJ, dan nomor dokumen tetap dipertahankan. Untuk saat ini, perubahan ditinjau melalui Detail Transaksi agar tidak ada keputusan otomatis yang berisiko.</p>
                </div>
            </div>
        </section>

        <x-section-card title="Filter antrean rekonsiliasi" description="Fokuskan daftar berdasarkan jenis masalah atau cari transaksi tertentu.">
            <form method="GET" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_14rem_auto]">
                <x-ui.field label="Cari transaksi">
                    <x-ui.input name="q" :value="$search" placeholder="Nomor bukti, uraian, penerima..." />
                </x-ui.field>
                <x-ui.field label="Jenis perhatian">
                    <x-ui.select name="filter">
                        <option value="" @selected($filter === '')>Semua perhatian</option>
                        <option value="changed" @selected($filter === 'changed')>Data ARKAS berubah</option>
                        <option value="missing" @selected($filter === 'missing')>Tidak muncul di sinkronisasi</option>
                        <option value="with_package" @selected($filter === 'with_package')>Sudah punya paket SPJ</option>
                    </x-ui.select>
                </x-ui.field>
                <div class="flex items-end gap-2">
                    <x-ui.button type="submit">Terapkan</x-ui.button>
                    <x-ui.button href="{{ route('reconciliation.index') }}" variant="secondary">Reset</x-ui.button>
                </div>
            </form>
        </x-section-card>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="font-bold text-slate-900">Transaksi yang perlu ditinjau</h2>
                        <p class="mt-1 text-sm text-slate-500">Bandingkan data sumber dengan data SPJ operator sebelum melanjutkan proses dokumen.</p>
                    </div>
                    <span class="text-xs font-semibold text-slate-400">{{ number_format($transactions->total(), 0, ',', '.') }} transaksi</span>
                </div>
            </div>

            <div class="grid gap-3 p-4 lg:hidden">
                @forelse($transactions as $transaction)
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-sm font-bold text-indigo-700">{{ $transaction->no_bukti }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia' }}</p>
                            </div>
                            <div class="flex flex-wrap justify-end gap-1.5">
                                @if($transaction->requires_reconciliation)
                                    <x-ui.status-badge status="REQUIRES_RECONCILIATION" />
                                @endif
                                @if(strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING')
                                    <x-ui.status-badge status="SOURCE_MISSING" />
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 rounded-lg bg-slate-50 p-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Data ARKAS / BKU</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $transaction->description ?: 'Uraian sumber tidak tersedia' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Penerima: {{ $transaction->recipient_name ?: 'Belum tersedia' }}</p>
                            </div>
                            <div class="border-t border-slate-200 pt-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-500">Data SPJ Operator</p>
                                <p class="mt-1 text-sm font-semibold text-indigo-900">{{ $transaction->payment_description ?: 'Uraian SPJ belum diisi' }}</p>
                                <p class="mt-1 text-xs text-indigo-600">Kuitansi: {{ $transaction->effective_receipt_recipient_name ?: 'Belum diisi' }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                            <div>
                                <p class="text-xs text-slate-400">Nilai bruto</p>
                                <p class="font-bold text-slate-900">{{ $rupiah($transaction->gross_amount) }}</p>
                            </div>
                            <a href="{{ route('transactions.show', $transaction->id) }}" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700">Tinjau detail →</a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50 p-8 text-center">
                        <p class="font-bold text-emerald-800">Tidak ada transaksi yang perlu direkonsiliasi.</p>
                        <p class="mt-1 text-sm text-emerald-700">Semua transaksi pada konteks aktif saat ini tidak memiliki tanda perubahan sumber.</p>
                    </div>
                @endforelse
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table data-pagination="server" class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Bukti / Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Perhatian</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Data ARKAS / BKU</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Data SPJ Operator</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Nilai</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($transactions as $transaction)
                            <tr class="align-top transition hover:bg-amber-50/40">
                                <td class="px-5 py-4">
                                    <p class="font-mono font-bold text-indigo-700">{{ $transaction->no_bukti }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $transaction->items_count }} rincian</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex max-w-48 flex-wrap gap-1.5">
                                        @if($transaction->requires_reconciliation)
                                            <x-ui.status-badge status="REQUIRES_RECONCILIATION" />
                                        @endif
                                        @if(strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING')
                                            <x-ui.status-badge status="SOURCE_MISSING" />
                                        @endif
                                        @if($transaction->spjPackage)
                                            <x-ui.status-badge :status="$transaction->spjPackage->status" />
                                        @endif
                                    </div>
                                    @if($transaction->source_missing_since)
                                        <p class="mt-2 text-xs text-slate-500">Sejak {{ $transaction->source_missing_since->translatedFormat('d M Y H:i') }}</p>
                                    @endif
                                </td>
                                <td class="max-w-sm px-4 py-4">
                                    <p class="font-semibold text-slate-800">{{ $transaction->description ?: 'Uraian sumber tidak tersedia' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Penerima: {{ $transaction->recipient_name ?: 'Belum tersedia' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $transaction->activity_code ?: 'Tanpa kode kegiatan' }} · {{ $transaction->account_code ?: 'Tanpa kode rekening' }}</p>
                                </td>
                                <td class="max-w-sm px-4 py-4">
                                    <p class="font-semibold text-indigo-900">{{ $transaction->payment_description ?: 'Uraian SPJ belum diisi' }}</p>
                                    <p class="mt-1 text-xs text-indigo-600">Kuitansi: {{ $transaction->effective_receipt_recipient_name ?: 'Belum diisi' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Kategori: {{ $transaction->spj_category ? str_replace('_', ' ', $transaction->spj_category) : 'Belum dipilih' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-bold text-slate-900">{{ $rupiah($transaction->gross_amount) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('transactions.show', $transaction->id) }}" class="inline-flex rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100">Tinjau detail →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><p class="font-bold text-emerald-700">Tidak ada transaksi yang perlu direkonsiliasi.</p><p class="mt-1 text-sm text-slate-500">Antrean akan muncul otomatis bila sinkronisasi mendeteksi perubahan sumber.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 bg-slate-50/40 px-5 py-4">
                {{ $transactions->links() }}
            </div>
        </section>
    </div>
</x-layouts.tailwind-app>
