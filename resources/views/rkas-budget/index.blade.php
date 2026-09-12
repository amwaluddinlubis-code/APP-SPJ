<x-layouts.tailwind-app>
    @php($rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))

    <div class="space-y-6">
        <x-page-header title="Penganggaran RKAS"
            :subtitle="'Pantau pagu RKAS dan realisasi BKU pada konteks ' . $contextLabel . '.'"
            kicker="Anggaran & Realisasi">
            <x-slot:actions>
                <form method="POST" action="{{ route('arkas.sync') }}"
                    data-confirm="Sinkronisasi akan memperbarui data RKAS dan BKU dari ARKAS. Lanjutkan?">
                    @csrf
                    <input type="hidden" name="confirm_sync" value="1">
                    <button
                        class="inline-flex w-fit items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/20 transition hover:bg-white/20">
                        <x-ui-icon name="sync" class="h-4 w-4" />
                        <span>Sinkron Semua ARKAS</span>
                    </button>
                </form>
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Total Anggaran" :value="$rupiah($budget)" hint="RKAS tersinkron"
                    value-class="text-[var(--theme-content-accent)]" icon="budget" icon-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Realisasi BKU" :value="$rupiah($spent)" hint="Belanja tercatat"
                    value-class="text-emerald-700" icon="transaction" icon-class="text-emerald-600" />
                <x-stat-item label="Sisa Anggaran" :value="$rupiah($remaining)" :hint="'Belum dibukukan '.$rupiah($underBudget).' · Kelebihan '.$rupiah($overBudget)"
                    icon="balance" icon-class="text-amber-600" />
                <x-stat-item label="Kegiatan RKAS" :value="number_format($activityCount, 0, ',', '.')" hint="Kegiatan tersinkron"
                    icon="work" icon-class="text-[var(--theme-content-accent)]" />
            </div>
        </x-page-header>

        <livewire:rkas-budget-filter />

        <x-section-card title="Rincian Hierarki RKAS" :description="'Pagu, realisasi, dan sisa ' . $filterContext . '.'" :padding="false">
            <x-slot:actions>
                <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">•
                    {{ number_format($treeTotals['items'], 0, ',', '.') }} data</span>
                <a class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition hover:brightness-95"
                    style="border-color: var(--ui-line); color: var(--ui-fg-muted); background: var(--ui-bg)"
                    href="{{ route('synced-data.show', 'rkas') }}">
                    <x-ui-icon name="database" class="h-4 w-4" />
                    <span>Data Mentah</span>
                </a>
            </x-slot:actions>

            @if($scope !== 'year' || filled($search) || filled($programFilter) || filled($subprogramFilter) || filled($activityFilter))
                <div class="mx-4 mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 rounded-lg border px-3 py-2 text-xs"
                    style="border-color: var(--ui-line); background: var(--ui-surface-soft); color: var(--ui-fg-muted)">
                    <span class="font-bold uppercase tracking-wide" style="color: var(--ui-fg-strong)">Subtotal
                        tersaring</span>
                    <span>Pagu tersaring <strong
                            style="color: var(--theme-content-accent)">{{ $rupiah($budget) }}</strong></span>
                    <span>Realisasi dibukukan <strong
                            class="text-emerald-700">{{ $rupiah($spent) }}</strong></span>
                    <span>Selisih <strong
                            style="color: var(--ui-fg-strong)">{{ $rupiah($remaining) }}</strong></span>
                </div>
            @endif

            <div class="mt-2 overflow-x-auto rounded-xl border" style="border-color: var(--ui-line)">
                <table class="min-w-[1100px] w-full divide-y text-sm" style="border-color: var(--ui-line)" data-pagination="none">
                    <thead style="background: var(--ui-surface-soft)">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Kode Program</th>
                            <th class="min-w-[260px] px-3 py-2 text-left text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Uraian</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Kode Rekening</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Volume</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Satuan</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Tarif Harga</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Pagu</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Realisasi</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                style="color: var(--ui-fg-muted)">Selisih</th>
                        </tr>
                    </thead>
                    <tbody x-data="{ open: {} }" class="divide-y" style="border-color: var(--ui-line)">
                        @forelse($hierarchyTree as $program)
                            <tr style="background: color-mix(in srgb, var(--theme-accent-soft) 45%, var(--ui-surface-base))">
                                <td colspan="6" class="px-3 py-1.5 text-xs">
                                    <button type="button"
                                        class="inline-flex items-center gap-2 text-left font-bold"
                                        style="color: var(--ui-fg-strong)"
                                        x-on:click="open['p-{{ $program['code'] }}'] = ! open['p-{{ $program['code'] }}']">
                                        <span
                                            class="inline-flex h-5 w-5 items-center justify-center rounded-full border"
                                            style="color: var(--theme-content-accent); border-color: color-mix(in srgb, var(--theme-content-accent) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 55%, var(--ui-surface-base))">
                                            <x-ui.icon name="chevron-down" size="xs"
                                                x-show="open['p-{{ $program['code'] }}']" />
                                            <x-ui.icon name="chevron-right" size="xs"
                                                x-show="! open['p-{{ $program['code'] }}']" />
                                        </span>
                                        <span class="font-mono">{{ $program['code'] }}</span>
                                        <span>· {{ $program['name'] }}</span>
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-3 py-1.5 text-right text-xs font-bold"
                                    style="color: var(--theme-content-accent)">{{ $rupiah($program['amount']) }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5 text-right text-xs font-semibold text-emerald-700">
                                    {{ $rupiah($program['realization']) }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5 text-right text-xs font-bold"
                                    style="color: var(--ui-fg-muted)">{{ $rupiah($program['remaining']) }}</td>
                            </tr>
                            @foreach ($program['subs'] as $sub)
                                <tr x-show="open['p-{{ $program['code'] }}']"
                                    style="background: color-mix(in srgb, var(--theme-accent-soft) 25%, var(--ui-surface-base))">
                                    <td colspan="6" class="px-3 py-1 pl-8 text-xs">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 text-left font-semibold"
                                            style="color: var(--ui-fg-strong)"
                                            x-on:click="open['s-{{ $sub['code'] }}'] = ! open['s-{{ $sub['code'] }}']">
                                            <span
                                                class="inline-flex h-4 w-4 items-center justify-center rounded-full border"
                                                style="color: var(--theme-accent-strong); border-color: color-mix(in srgb, var(--theme-accent-strong) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 35%, var(--ui-surface-base))">
                                                <x-ui.icon name="chevron-down" size="xs"
                                                    x-show="open['s-{{ $sub['code'] }}']" />
                                                <x-ui.icon name="chevron-right" size="xs"
                                                    x-show="! open['s-{{ $sub['code'] }}']" />
                                            </span>
                                            <span class="font-mono">{{ $sub['code'] }}</span>
                                            <span>· {{ $sub['name'] }}</span>
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                        style="color: var(--theme-content-accent)">{{ $rupiah($sub['amount']) }}</td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right text-xs text-emerald-700">
                                        {{ $rupiah($sub['realization']) }}</td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                        style="color: var(--ui-fg-muted)">{{ $rupiah($sub['remaining']) }}</td>
                                </tr>
                                @foreach ($sub['activities'] as $activity)
                                    <tr x-show="open['p-{{ $program['code'] }}'] && open['s-{{ $sub['code'] }}']"
                                        style="background: var(--ui-surface-soft)">
                                        <td colspan="6" class="px-3 py-1 pl-14 text-xs">
                                            <button type="button"
                                                class="inline-flex items-center gap-2 text-left font-semibold"
                                                style="color: var(--ui-fg-strong)"
                                                x-on:click="open['k-{{ $activity['code'] }}'] = ! open['k-{{ $activity['code'] }}']">
                                                <span
                                                    class="inline-flex h-4 w-4 items-center justify-center rounded-full border"
                                                    style="color: var(--theme-accent-strong); border-color: color-mix(in srgb, var(--theme-accent-strong) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 35%, var(--ui-surface-base))">
                                                    <x-ui.icon name="chevron-down" size="xs"
                                                        x-show="open['k-{{ $activity['code'] }}']" />
                                                    <x-ui.icon name="chevron-right" size="xs"
                                                        x-show="! open['k-{{ $activity['code'] }}']" />
                                                </span>
                                                <span class="font-mono">{{ $activity['code'] }}</span>
                                                <span>· {{ $activity['name'] }}</span>
                                            </button>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                            style="color: var(--theme-content-accent)">{{ $rupiah($activity['amount']) }}</td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right text-xs text-emerald-700">
                                            {{ $rupiah($activity['realization']) }}</td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                            style="color: var(--ui-fg-muted)">{{ $rupiah($activity['remaining']) }}</td>
                                    </tr>
                                    @foreach ($activity['items'] as $item)
                                        <tr x-show="open['p-{{ $program['code'] }}'] && open['s-{{ $sub['code'] }}'] && open['k-{{ $activity['code'] }}']"
                                            class="text-xs">
                                            <td class="whitespace-nowrap px-3 py-1 pl-20 font-mono"
                                                style="color: var(--ui-fg-muted)">{{ trim((string) $item->activity_code, '.') }}</td>
                                            <td class="px-3 py-1">
                                                <p class="font-semibold leading-tight" style="color: var(--ui-fg-strong)">
                                                    {{ $item->description ?: 'Tanpa uraian' }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-1 font-mono"
                                                style="color: var(--ui-fg-muted)">{{ $item->account_code ?: 'Tanpa kode rekening' }}</td>
                                            <td class="whitespace-nowrap px-3 py-1 text-right">
                                                {{ rtrim(rtrim(number_format($item->volume, 2, ',', '.'), '0'), ',') }}</td>
                                            <td class="whitespace-nowrap px-3 py-1">{{ $item->unit }}</td>
                                            <td class="whitespace-nowrap px-3 py-1 text-right">
                                                {{ $rupiah($item->unit_price) }}</td>
                                            <td class="whitespace-nowrap px-3 py-1 text-right font-semibold"
                                                style="color: var(--theme-content-accent)">{{ $rupiah($item->display_amount) }}</td>
                                            <td class="whitespace-nowrap px-3 py-1 text-right text-emerald-700">
                                                {{ $rupiah($item->realization) }}</td>
                                            <td class="whitespace-nowrap px-3 py-1 text-right"
                                                style="color: var(--ui-fg-muted)">{{ $rupiah($item->variance) }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-14 text-center">
                                    <p class="text-sm font-semibold" style="color: var(--ui-fg-strong)">Belum ada
                                        RKAS.</p>
                                    <p class="mt-1 text-base" style="color: var(--ui-fg-muted)">Jalankan
                                        sinkronisasi atau ubah kata kunci pencarian.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-section-card>
    </div>
</x-layouts.tailwind-app>
