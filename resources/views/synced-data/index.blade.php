<x-layouts.tailwind-app>
    @php
        $groups = collect($tables)->groupBy('group', true);
        $totalRows = array_sum($counts);
        $currencyColumns = ['ANGGARAN', 'NILAI', 'BRUTO', 'PAJAK', 'DIBAYARKAN', 'HARGA_SATUAN', 'TARIF_HARIAN'];
        $booleanColumns = ['AKTIF', 'HONOR', 'PPN', 'PPH21', 'PPH22', 'PPH23', 'PPH4', 'SSPD', 'BADAN_USAHA', 'DARI_ARKAS', 'PENERIMA_KUITANSI'];
    @endphp

    <div class="space-y-6">
        <x-page-header
            title="Data Hasil Sinkron"
            subtitle="Data baca-saja dari ARKAS dan hasil pengolahan SPJ pada sekolah serta tahun aktif."
            kicker="Pusat Data Sekolah"
            icon="database"
        >
            <x-slot:actions>
                <span class="ui-btn ui-btn-secondary px-3 py-2 text-base">{{ number_format($totalRows, 0, ',', '.') }} total baris</span>
                @if($type !== 'overview')
                    <x-ui.button :href="route('synced-data.index')">Ringkasan Data</x-ui.button>
                @endif
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kelompok Tabel</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $groups->count() }} kelompok</p>
                </div>
                <div class="px-5 py-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tabel Terdaftar</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ count($tables) }} tabel</p>
                </div>
                <div class="px-5 py-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Mode Tampilan</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700">Baca-saja</p>
                </div>
            </div>
        </x-page-header>

        <nav class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 shadow" aria-label="Navigasi kelompok data">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('synced-data.index') }}" class="rounded-lg px-3 py-2 text-base font-semibold transition {{ $type === 'overview' ? 'bg-[var(--theme-content-accent)] text-white shadow' : 'text-[var(--ui-fg)] hover:bg-[var(--ui-surface-soft)]' }}">Semua Data</a>
                @foreach($groups as $group => $items)
                    <span class="rounded-lg bg-[var(--ui-surface-soft)] px-3 py-2 text-base font-semibold text-[var(--ui-fg)]">
                        {{ $group }} <span class="ml-1 text-[var(--ui-fg-muted)]">{{ $items->count() }}</span>
                    </span>
                @endforeach
            </div>
        </nav>

        @if($type === 'overview')
            @foreach($groups as $group => $items)
                <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow sm:p-6">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--theme-content-accent)] text-base font-black text-white">{{ $loop->iteration }}</span>
                            <div>
                                <h2 class="font-bold text-[var(--ui-fg-strong)]">{{ $group }}</h2>
                                <p class="mt-0.5 text-base text-[var(--ui-fg-muted)]">{{ $items->count() }} tabel tersedia untuk diperiksa.</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-[var(--ui-surface-soft)] px-3 py-1 text-xs font-bold text-[var(--ui-fg)]">{{ number_format($items->keys()->sum(fn ($key) => $counts[$key]), 0, ',', '.') }} baris</span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($items as $key => $item)
                            <a href="{{ route('synced-data.show', $key) }}" class="group rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 transition hover:-translate-y-0.5 hover:border-[var(--ui-line-strong)] hover:bg-[var(--ui-surface-soft)] hover:shadow-md">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--theme-content-accent)] text-xs font-bold text-white">{{ strtoupper(substr($item['label'], 0, 2)) }}</span>
                                    <span class="text-lg font-bold text-[var(--ui-fg-strong)]">{{ number_format($counts[$key], 0, ',', '.') }}</span>
                                </div>
                                <h3 class="mt-4 font-semibold text-[var(--ui-fg-strong)] group-hover:text-[var(--theme-content-accent)]">{{ $item['label'] }}</h3>
                                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Buka tabel dan periksa data sumber.</p>
                                <p class="mt-4 text-xs font-bold text-[var(--theme-content-accent)]">Lihat data →</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @else
            <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
                <x-ui.toolbar class="border-b border-[var(--ui-line)] px-5 py-3 sm:px-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-md bg-[var(--ui-surface-soft)] px-2.5 py-1 text-xs font-bold text-[var(--ui-fg)]">{{ $table['group'] }}</span>
                            <span class="text-xs font-medium text-[var(--ui-fg-muted)]">{{ number_format($counts[$type], 0, ',', '.') }} baris</span>
                        </div>
                        <h2 class="mt-2 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $table['label'] }}</h2>
                        <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Periksa hasil sinkronisasi sebelum melanjutkan proses SPJ.</p>
                    </div>
                    <x-slot:actions>
                        <x-page-table-per-page :total="$rows->total()" name="perPage" :current="request('perPage', 15)" />
                        <x-ui.button variant="secondary" :href="route('synced-data.index')">← Kembali ke ringkasan</x-ui.button>
                    </x-slot:actions>
                </x-ui.toolbar>

                <x-ui.table min-width="960px" pagination="server">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 w-14 bg-[var(--ui-surface-soft)] text-center">No</th>
                            @foreach($table['select'] as $column)
                                @php($parts = preg_split('/\s+as\s+/i', $column))
                                @php($label = strtoupper(end($parts)))
                                <th class="whitespace-nowrap">{{ str_replace('_', ' ', $label) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                            <tr>
                                <td class="sticky left-0 z-10 bg-[var(--ui-surface-base)] text-center text-xs font-semibold text-[var(--ui-fg-muted)]">{{ $rows->firstItem() + $index }}</td>
                                @foreach($table['select'] as $column)
                                    @php($parts = preg_split('/\s+as\s+/i', $column))
                                    @php($label = strtoupper(end($parts)))
                                    @php($values = (array) $row)
                                    @php($value = $values[$label] ?? null)
                                    <td class="max-w-xs align-top text-[var(--ui-fg)]">
                                        @if(in_array($label, $currencyColumns, true))
                                            <span class="whitespace-nowrap font-semibold text-[var(--ui-fg-strong)]">Rp {{ number_format((float) $value, 0, ',', '.') }}</span>
                                        @elseif(in_array($label, $booleanColumns, true))
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ (bool) $value ? 'bg-emerald-50 text-emerald-700' : 'bg-[var(--ui-surface-soft)] text-[var(--ui-fg-muted)]' }}">{{ (bool) $value ? 'Ya' : 'Tidak' }}</span>
                                        @elseif($value === null || $value === '')
                                            <span class="text-[var(--ui-fg-muted)]">—</span>
                                        @else
                                            <span class="{{ str_contains($label, 'KODE') || str_contains($label, 'ID_') || str_contains($label, 'NIP') ? 'font-mono text-xs text-[var(--theme-content-accent)]' : '' }}">{{ $value }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($table['select']) + 1 }}" class="empty-cell">
                                    <p class="font-semibold text-[var(--ui-fg-strong)]">Belum ada data tersimpan.</p>
                                    <p class="mt-1 text-base text-[var(--ui-fg-muted)]">Jalankan Sinkron Semua ARKAS untuk mengisi tabel ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>

                <x-ui.server-pagination :paginator="$rows" noun="data" />
            </section>
        @endif
    </div>
</x-layouts.tailwind-app>
