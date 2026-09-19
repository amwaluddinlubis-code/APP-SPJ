@php
    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $isFiltered = $q !== '' || $mode !== 'semua' || $periode;
    $display = $isFiltered ? $filteredSummary : $summary;
@endphp
<div class="space-y-6">
    <x-page-header
        title="Pajak"
        subtitle="Rekap pajak dari transaksi BKU pada konteks tahun dan sumber dana aktif."
        kicker="Hasil Sinkronisasi ARKAS"
    >
        <div class="border-b border-[var(--ui-line)] px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-400 sm:px-6">{{ $isFiltered ? 'Subtotal Periode Terpilih' : 'Total Tahunan '.$year->year }}</div>
        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
            <x-stat-item label="Transaksi Pajak" :value="number_format($display->count, 0, ',', '.')" hint="{{ $isFiltered ? 'Sesuai filter aktif' : 'Transaksi mengandung pajak' }}" />
            <x-stat-item label="PPN" :value="$rupiah($display->ppn)" hint="{{ $isFiltered ? 'Sesuai filter aktif' : 'Total PPN tahunan' }}" value-class="text-indigo-700" />
            <x-stat-item label="PPh" :value="$rupiah($display->pph21 + $display->pph22 + $display->pph23 + $display->pph4)" hint="{{ $isFiltered ? 'Sesuai filter aktif' : 'Gabungan PPh' }}" value-class="text-rose-700" />
            <x-stat-item label="Total Pajak" :value="$rupiah($display->total)" hint="{{ $isFiltered ? 'Sesuai filter aktif' : 'Total seluruh pajak' }}" value-class="text-amber-700" />
        </div>
    </x-page-header>

    <section class="ui-filter-panel">
        <div class="grid items-end gap-x-4 gap-y-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)_auto]">
            <div class="sm:col-span-2 lg:col-span-1">
                <span id="tax-filter-mode-label" class="ui-filter-label">Periode</span>
                <div class="flex w-fit max-w-full overflow-x-auto rounded-lg border border-[var(--ui-line)] text-sm" role="group" aria-labelledby="tax-filter-mode-label">
                    @foreach ($this->modes() as [$modeOption, $label])
                        <button type="button" wire:click="setMode('{{ $modeOption }}')" class="whitespace-nowrap px-3 py-2 transition {{ $mode === $modeOption ? 'font-bold text-white' : 'text-[var(--ui-fg-muted)]' }}" style="{{ $mode === $modeOption ? 'background: var(--theme-action-bg);' : 'background: var(--ui-surface-base);' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="ui-filter-label" for="tax-filter-periode">Pilih periode</label>
                <x-ui.select id="tax-filter-periode" wire:model.live="periode">
                    <option value="">{{ $mode === 'semua' ? 'Semua periode' : 'Pilih '.$mode }}</option>
                    @if ($mode === 'semester')
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    @elseif ($mode === 'triwulan')
                        @foreach (range(1, 4) as $option)
                            <option value="{{ $option }}">Triwulan {{ $option }}</option>
                        @endforeach
                    @elseif ($mode === 'bulan')
                        @foreach (range(1, 12) as $option)
                            <option value="{{ $option }}">{{ \Carbon\Carbon::create()->month($option)->translatedFormat('F') }}</option>
                        @endforeach
                    @endif
                </x-ui.select>
            </div>
            <div>
                <label class="ui-filter-label" for="tax-filter-search">Cari</label>
                <x-ui.input id="tax-filter-search" wire:model.live.debounce.500ms="q" placeholder="Cari bukti atau penerima" />
            </div>
            @if($q !== '' || $mode !== 'semua' || $periode)
                <div class="sm:col-span-2 lg:col-span-1">
                    <x-ui.button type="button" variant="secondary" wire:click="resetFilters" icon="close">Hapus Saringan</x-ui.button>
                </div>
            @endif
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
        <x-ui.toolbar class="border-b border-[var(--ui-line)] px-5 py-3 sm:px-6">
            <div>
                <h2 class="font-bold" style="color: var(--ui-fg)">Daftar Pajak Tersinkron</h2>
                <p class="mt-0.5 text-sm" style="color: var(--ui-fg-muted)">Satu nomor bukti ditampilkan satu kali.</p>
            </div>
            <x-slot:actions>
                <div class="flex items-center gap-2 text-xs">
                    <label for="tax-filter-per-page" class="font-semibold" style="color: var(--ui-fg-muted)">Baris</label>
                    <select id="tax-filter-per-page" wire:model.live="perPage" aria-label="Baris per halaman"
                        class="ui-select !min-h-9 !w-auto !py-1.5 !text-xs">
                        <option value="15">15 baris</option>
                        <option value="25">25 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                    </select>
                    <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">• {{ number_format($transactions?->total() ?? 0, 0, ',', '.') }} data</span>
                </div>
            </x-slot:actions>
        </x-ui.toolbar>

        <div class="overflow-x-auto">
            <table data-pagination="server" class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                <thead class="bg-[var(--ui-surface-soft)]"><tr>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Bukti / Tanggal</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Penerima</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPN</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 21</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 22</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 23</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">PPh 4 / SSPD</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Total</th>
                </tr></thead>
                <tbody class="divide-y divide-[var(--ui-line)]">
                    @forelse($transactions as $transaction)
                        <tr wire:key="tax-row-{{ $transaction->id }}" class="transition hover:bg-amber-50/50">
                            <td class="px-5 py-4">
                                @php($taxDetailIdentifier = $transaction->getAttribute('read_context_path') === 'v2_compat' ? $transaction->source_key : $transaction)
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <a href="{{ route('transactions.show', $taxDetailIdentifier) }}" class="font-mono font-bold text-indigo-700">{{ $transaction->no_bukti }}</a>
                                    @if($transaction->getAttribute('read_context_path') === 'v2_compat')<x-ui.status-badge status="READY" label="Baca saja" size="xs" />@endif
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?? '-' }}</p>
                            </td>
                            <td class="max-w-xs px-4 py-4"><p class="truncate font-semibold text-slate-800">{{ $transaction->recipient_name ?: 'Penerima belum diisi' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $transaction->description ?: 'Tanpa uraian' }}</p></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->ppn) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph21) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph22) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph23) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($transaction->pph4 + $transaction->sspd) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-bold text-amber-700">{{ $rupiah($transaction->tax_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14"><x-ui.empty-state title="Belum ada pajak tersinkron." description="Data akan muncul setelah sinkronisasi BKU yang memiliki pajak." icon="inbox" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-ui.server-pagination :paginator="$transactions" noun="transaksi" />
    </section>
</div>
