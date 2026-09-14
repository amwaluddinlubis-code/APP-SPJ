@php($rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.'))

<div class="space-y-6">
    <x-section-card title="Modul 1 — Saran Pagu Awal" description="Baseline pagu tahun lalu per kegiatan untuk bahan musyawarah." :padding="false">
        @if($initial['base_year'] === null)
            <p class="px-5 py-6 text-sm" style="color: var(--ui-fg-muted)">Belum ada data RKAS tahun sebelumnya pada sumber dana aktif, sehingga saran pagu awal belum dapat disusun.</p>
        @else
            <p class="border-b border-[var(--ui-line)] px-5 py-3 text-sm" style="color: var(--ui-fg-muted)">
                {{ $initial['applicable'] ? 'Basis' : 'Tahun berjalan sudah memiliki pagu dan realisasi. Basis' }} tahun {{ $initial['base_year'] }}. Angka saran adalah titik awal musyawarah, bukan angka final.
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]"><tr>
                        <th class="px-5 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Kegiatan</th>
                        <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Pagu Lalu</th>
                        <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Realisasi Lalu</th>
                        <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Serapan</th>
                        <th class="px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Saran Pagu</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)] text-sm">
                        @forelse($initialPaginator as $item)
                            <tr class="transition hover:bg-indigo-50/50">
                                <td class="px-5 py-4"><p class="font-mono text-[13px] font-semibold text-sky-700">{{ $item['activity_code'] }}</p><p class="mt-1 line-clamp-2 text-[13px] text-slate-500">{{ $item['activity_name'] }}</p></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($item['last_budget']) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right text-emerald-700">{{ $rupiah($item['last_spent']) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">{{ number_format($item['absorption'], 1, ',', '.') }}%</td>
                                <td class="whitespace-nowrap px-4 py-4 text-[13px] font-semibold text-slate-600">{{ $item['absorption_label'] }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-indigo-700">{{ $rupiah($item['suggested']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada kegiatan pada tahun pembanding.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($initialPaginator->hasPages())<x-ui.server-pagination :paginator="$initialPaginator" noun="kegiatan" />@endif
        @endif
    </x-section-card>

    <x-section-card title="Modul 2 — Saran Sisa Pagu" description="Panduan realisasi dan realokasi sisa pagu berjalan per kegiatan." :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                <thead class="bg-[var(--ui-surface-soft)]"><tr>
                    <th class="px-5 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Kegiatan</th>
                    <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Pagu</th>
                    <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Realisasi</th>
                    <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Sisa</th>
                    <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Serapan</th>
                    <th class="px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-5 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Saran</th>
                </tr></thead>
                <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)] text-sm">
                    @forelse($remainingPaginator as $item)
                        <tr class="transition hover:bg-indigo-50/50">
                            <td class="px-5 py-4"><p class="font-mono text-[13px] font-semibold text-sky-700">{{ $item['activity_code'] }}</p><p class="mt-1 line-clamp-2 text-[13px] text-slate-500">{{ $item['activity_name'] }}</p></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ $rupiah($item['budget']) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-emerald-700">{{ $rupiah($item['spent']) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-semibold {{ $item['remaining'] < 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $rupiah($item['remaining']) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">{{ number_format($item['absorption'], 1, ',', '.') }}%</td>
                            <td class="whitespace-nowrap px-4 py-4 text-[13px] font-semibold text-slate-600">{{ $item['status'] }}</td>
                            <td class="px-5 py-4 text-[13px] text-slate-600">{{ $item['suggestion'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">Belum ada data RKAS pada tahun dan sumber dana aktif.</td></tr>
                    @endforelse
                </tbody>
                @if(count($remaining['rows']) > 0)<tfoot class="bg-[var(--ui-surface-soft)]"><tr>
                    <td class="px-5 py-3 text-sm font-bold">Total</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold">{{ $rupiah($remaining['totals']['budget']) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold">{{ $rupiah($remaining['totals']['spent']) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold">{{ $rupiah($remaining['totals']['remaining']) }}</td>
                    <td colspan="3"></td>
                </tr></tfoot>@endif
            </table>
        </div>
        @if($remainingPaginator->hasPages())<x-ui.server-pagination :paginator="$remainingPaginator" noun="kegiatan" />@endif
    </x-section-card>
</div>
