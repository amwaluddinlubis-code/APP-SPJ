<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.'))

    <div class="space-y-6">
        <x-page-header
            title="Penganggaran RKAS"
            subtitle="Pantau pagu RKAS dan realisasi BKU pada konteks tahun serta sumber dana aktif."
            kicker="Anggaran & Realisasi"
        >
            <x-slot:breadcrumb>
                <x-breadcrumb :items="[
                    ['label' => 'Penganggaran RKAS'],
                ]" />
            </x-slot:breadcrumb>

            <x-slot:actions>
                <form method="POST" action="{{ route('arkas.sync') }}" data-confirm="Sinkronisasi akan memperbarui data RKAS dan BKU dari ARKAS. Lanjutkan?">
                    @csrf
                    <input type="hidden" name="confirm_sync" value="1">
                    <button class="inline-flex w-fit rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/20 transition hover:bg-white/20">
                        Sinkron Semua ARKAS
                    </button>
                </form>
            </x-slot:actions>

            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Total Anggaran" :value="$rupiah($budget)" hint="RKAS tersinkron" value-class="text-indigo-700" />
                <x-stat-item label="Realisasi BKU" :value="$rupiah($spent)" hint="Belanja tercatat" value-class="text-emerald-700" />
                <x-stat-item
                    label="Sisa Anggaran"
                    :value="$rupiah(abs($remaining))"
                    :hint="$remaining < 0 ? 'Melewati anggaran' : 'Belum direalisasikan'"
                    :value-class="$remaining < 0 ? 'text-rose-600' : 'text-slate-800'"
                />
                <x-stat-item label="Kegiatan RKAS" :value="number_format($activityCount, 0, ',', '.')" hint="Kegiatan tersinkron" value-class="text-slate-800" />
            </div>
        </x-page-header>

        <section class="flex flex-col gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 sm:flex-row sm:items-center sm:px-6">
            <span class="rounded-lg bg-sky-600 px-3 py-2 text-xs font-bold text-white">SISA TERSEDIA</span>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-sky-700">Masih bisa direalisasikan</p>
                <p class="text-xl font-bold text-sky-900">{{ $rupiah($underBudget) }}</p>
            </div>
            <div class="flex gap-6 text-sm sm:ml-auto">
                <div>
                    <p class="text-slate-500">Selisih sisa</p>
                    <strong class="text-emerald-700">{{ $rupiah($underBudget) }}</strong>
                </div>
                <div>
                    <p class="text-slate-500">Selisih kurang</p>
                    <strong class="text-rose-600">{{ $rupiah($overBudget) }}</strong>
                </div>
            </div>
        </section>

        <x-section-card
            title="Rincian Penganggaran RKAS"
            description="Pagu anggaran dan realisasi BKU pada konteks aktif."
            :padding="false"
        >
            <x-slot:actions>
                <form method="GET" class="flex">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <input
                        name="q"
                        value="{{ $search }}"
                        class="w-64 rounded-l-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Cari rekening atau kegiatan"
                    >
                    <button class="rounded-r-lg bg-indigo-600 px-3 py-2 text-sm font-bold text-white">Cari</button>
                </form>

                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="q" value="{{ $search }}">
                    <label for="rkas-per-page" class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Baris</label>
                    <select
                        id="rkas-per-page"
                        name="per_page"
                        onchange="this.form.submit()"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm"
                        style="border-color: var(--ui-line); background: var(--ui-bg); color: var(--ui-fg)"
                    >
                        @foreach([10, 15, 30, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} baris</option>
                        @endforeach
                    </select>
                </form>

                <a class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" href="{{ route('synced-data.show', 'rkas') }}">
                    Data Mentah
                </a>
            </x-slot:actions>

            <div class="overflow-x-auto">
                <table data-pagination="server" class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500">No</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Kode Rekening</th>
                            <th class="min-w-[260px] px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Uraian / Barang</th>
                            <th class="min-w-[220px] px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Kegiatan</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Volume</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Satuan</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Harga Satuan</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Anggaran</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Realisasi</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($items as $index => $item)
                            <tr class="transition hover:bg-indigo-50/50">
                                <td class="px-5 py-4 text-center text-xs font-semibold text-slate-400">{{ $items->firstItem() + $index }}</td>
                                <td class="px-4 py-4"><span class="font-mono text-xs font-bold text-indigo-700">{{ $item->account_code ?: '—' }}</span></td>
                                <td class="px-4 py-4"><p class="line-clamp-2 font-semibold text-slate-800">{{ $item->description ?: 'Tanpa uraian' }}</p><p class="mt-1 font-mono text-[11px] text-slate-400">{{ $item->source_rapbs_id }}</p></td>
                                <td class="px-4 py-4"><p class="font-mono text-xs font-semibold text-sky-700">{{ $item->activity_code ?: '—' }}</p><p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $item->activity_name ?: 'Kegiatan belum diisi' }}</p></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ rtrim(rtrim(number_format($item->volume, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-4 py-4 text-slate-500">{{ $item->unit }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($item->unit_price) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-indigo-700">{{ $rupiah($item->amount) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right font-medium text-emerald-700">{{ $rupiah($item->realization) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-semibold {{ $item->variance < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ $item->variance < 0 ? '- ' : '' }}{{ $rupiah(abs($item->variance)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-14 text-center">
                                    <p class="font-semibold text-slate-700">Belum ada RKAS.</p>
                                    <p class="mt-1 text-base text-slate-500">Jalankan sinkronisasi atau ubah kata kunci pencarian.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                style="border-color: var(--ui-line); background: var(--ui-bg-subtle)"
            >
                <span class="text-sm" style="color: var(--ui-fg-muted)">Menampilkan {{ $items->firstItem() ?: 0 }}–{{ $items->lastItem() ?: 0 }} dari {{ $items->total() }} data</span>
                <div class="[&>nav]:text-sm">{{ $items->links() }}</div>
            </div>
        </x-section-card>
    </div>
</x-layouts.tailwind-app>
