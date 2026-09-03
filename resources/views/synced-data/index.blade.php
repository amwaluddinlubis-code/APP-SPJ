<x-layouts.tailwind-app>
    @php
        $groups = collect($tables)->groupBy('group', true);
        $totalRows = array_sum($counts);
        $groupStyles = [
            'REFERENSI / MASTER' => ['badge' => 'bg-indigo-100 text-indigo-700', 'icon' => 'bg-indigo-600'],
            'ANGGARAN & BUKU KAS' => ['badge' => 'bg-sky-100 text-sky-700', 'icon' => 'bg-sky-600'],
            'HASIL SPJ' => ['badge' => 'bg-emerald-100 text-emerald-700', 'icon' => 'bg-emerald-600'],
            'ENTITAS SPJ / DOMAIN NO_*' => ['badge' => 'bg-violet-100 text-violet-700', 'icon' => 'bg-violet-600'],
            'RIWAYAT OPERASIONAL' => ['badge' => 'bg-amber-100 text-amber-700', 'icon' => 'bg-amber-600'],
        ];
        $currencyColumns = ['ANGGARAN', 'NILAI', 'BRUTO', 'PAJAK', 'DIBAYARKAN', 'HARGA_SATUAN', 'TARIF_HARIAN'];
        $booleanColumns = ['AKTIF', 'HONOR', 'PPN', 'PPH21', 'PPH22', 'PPH23', 'PPH4', 'SSPD', 'BADAN_USAHA', 'DARI_ARKAS', 'PENERIMA_KUITANSI'];
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold tracking-wider text-sky-200"><span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-white/15 text-white ring-1 ring-white/20">DB</span> PUSAT DATA SEKOLAH</div>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Data Hasil Sinkron</h1>
                        <p class="mt-1 text-base text-indigo-100">Data baca-saja dari ARKAS dan hasil pengolahan SPJ pada sekolah serta tahun aktif.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-base font-medium text-white">{{ number_format($totalRows, 0, ',', '.') }} total baris</span>
                        @if($type !== 'overview')<a href="{{ route('synced-data.index') }}" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-base font-semibold text-white shadow transition hover:bg-indigo-700">Ringkasan Data</a>@endif
                    </div>
                </div>
            </div>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-3.5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kelompok Tabel</p><p class="mt-1 text-lg font-bold text-slate-800">{{ $groups->count() }} kelompok</p></div>
                <div class="px-5 py-3.5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tabel Terdaftar</p><p class="mt-1 text-lg font-bold text-slate-800">{{ count($tables) }} tabel</p></div>
                <div class="px-5 py-3.5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mode Tampilan</p><p class="mt-1 text-lg font-bold text-emerald-700">Baca-saja</p></div>
            </div>
        </section>

        <nav class="rounded-2xl border border-slate-200 bg-white p-3 shadow" aria-label="Navigasi kelompok data">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('synced-data.index') }}" class="rounded-lg px-3 py-2 text-base font-semibold transition {{ $type === 'overview' ? 'bg-slate-900 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">Semua Data</a>
                @foreach($groups as $group => $items)
                    @php($style = $groupStyles[$group] ?? $groupStyles['REFERENSI / MASTER'])
                    <span class="rounded-lg px-3 py-2 text-base font-semibold {{ $style['badge'] }}">{{ $group }} <span class="ml-1 opacity-70">{{ $items->count() }}</span></span>
                @endforeach
            </div>
        </nav>

        @if($type === 'overview')
            @foreach($groups as $group => $items)
                @php($style = $groupStyles[$group] ?? $groupStyles['REFERENSI / MASTER'])
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow sm:p-6">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-base font-black text-white {{ $style['icon'] }}">{{ $loop->iteration }}</span><div><h2 class="font-bold text-slate-800">{{ $group }}</h2><p class="mt-0.5 text-base text-slate-500">{{ $items->count() }} tabel tersedia untuk diperiksa.</p></div></div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $style['badge'] }}">{{ number_format($items->keys()->sum(fn ($key) => $counts[$key]), 0, ',', '.') }} baris</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($items as $key => $item)
                            <a href="{{ route('synced-data.show', $key) }}" class="group rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                                <div class="flex items-start justify-between gap-3"><span class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-xs font-bold text-white {{ $style['icon'] }}">{{ strtoupper(substr($item['label'], 0, 2)) }}</span><span class="text-lg font-bold text-slate-900">{{ number_format($counts[$key], 0, ',', '.') }}</span></div>
                                <h3 class="mt-4 font-semibold text-slate-800 group-hover:text-indigo-700">{{ $item['label'] }}</h3>
                                <p class="mt-1 text-xs text-slate-500">Buka tabel dan periksa data sumber.</p>
                                <p class="mt-4 text-xs font-bold text-indigo-600">Lihat data →</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @else
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div><div class="flex items-center gap-2"><span class="rounded-md px-2.5 py-1 text-xs font-bold {{ ($groupStyles[$table['group']] ?? $groupStyles['REFERENSI / MASTER'])['badge'] }}">{{ $table['group'] }}</span><span class="text-xs font-medium text-slate-400">{{ number_format($counts[$type], 0, ',', '.') }} baris</span></div><h2 class="mt-2 text-lg font-bold text-slate-800">{{ $table['label'] }}</h2><p class="mt-1 text-base text-slate-500">Periksa hasil sinkronisasi sebelum melanjutkan proses SPJ.</p></div>
                        <a href="{{ route('synced-data.index') }}" class="inline-flex w-fit rounded-lg border border-slate-300 bg-white px-3 py-2 text-base font-semibold text-slate-700 transition hover:bg-slate-50">← Kembali ke ringkasan</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-base">
                        <thead class="bg-slate-50"><tr><th class="sticky left-0 z-10 w-14 bg-slate-50 px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500">No</th>
                            @foreach($table['select'] as $column)
                                @php($parts = preg_split('/\s+as\s+/i', $column))
                                @php($label = strtoupper(end($parts)))
                                <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $label) }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($rows as $index => $row)
                                <tr class="transition hover:bg-indigo-50/50"><td class="sticky left-0 z-10 bg-white px-4 py-3 text-center text-xs font-semibold text-slate-400">{{ $rows->firstItem() + $index }}</td>
                                    @foreach($table['select'] as $column)
                                        @php($parts = preg_split('/\s+as\s+/i', $column))
                                        @php($label = strtoupper(end($parts)))
                                        @php($values = (array) $row)
                                        @php($value = $values[$label] ?? null)
                                        <td class="max-w-xs px-4 py-3 align-top text-slate-700">
                                            @if(in_array($label, $currencyColumns, true))<span class="whitespace-nowrap font-semibold text-slate-800">Rp {{ number_format((float) $value, 0, ',', '.') }}</span>
                                            @elseif(in_array($label, $booleanColumns, true))<span class="rounded-full px-2 py-1 text-xs font-semibold {{ (bool) $value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ (bool) $value ? 'Ya' : 'Tidak' }}</span>
                                            @elseif($value === null || $value === '')<span class="text-slate-300">—</span>
                                            @else<span class="{{ str_contains($label, 'KODE') || str_contains($label, 'ID_') || str_contains($label, 'NIP') ? 'font-mono text-xs text-indigo-700' : '' }}">{{ $value }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($table['select']) + 1 }}" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">Belum ada data tersimpan.</p><p class="mt-1 text-base text-slate-500">Jalankan Sinkron Semua ARKAS untuk mengisi tabel ini.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-100 px-5 py-4 bg-slate-50/30">
                    <x-page-table-per-page :total="$rows->total()" />
                    <div class="w-full sm:w-auto">{{ $rows->links() }}</div>
                </div>
            </section>
        @endif
    </div>
</x-layouts.tailwind-app>
