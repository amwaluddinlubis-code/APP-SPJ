<x-layouts.tailwind-app>
    @php($rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))

    <div class="space-y-6">
        <x-page-header title="Penganggaran RKAS"
            subtitle="Pantau pagu RKAS dan realisasi BKU pada konteks tahun serta sumber dana aktif."
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

        <section class="ui-filter-panel" x-data="{ scope: @js($scope) }">
            <form method="GET" class="ui-filter-grid lg:!grid-cols-4">
                <div>
                    <label class="ui-filter-label" for="rkas-scope">Tampilan periode</label>
                    <x-ui.select id="rkas-scope" name="scope" x-model="scope" x-on:change="$el.form.submit()">
                        <option value="year">Tahun anggaran</option>
                        <option value="month">Bulan</option>
                        <option value="quarter">Triwulan</option>
                        <option value="semester">Semester</option>
                    </x-ui.select>
                </div>
                <div x-show="scope !== 'year'" x-cloak>
                    <label class="ui-filter-label" for="rkas-scope-value">Periode</label>
                    <x-ui.select id="rkas-scope-value" name="scope_value" x-on:change="$el.form.submit()">
                        <option value="">Pilih periode</option>
                        <optgroup label="Bulan" x-show="scope === 'month'">
                            @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $month)
                                <option value="{{ $loop->iteration }}" x-show="scope === 'month'"
                                    @selected($scope === 'month' && $scopeValue === $loop->iteration)>{{ $month }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Triwulan" x-show="scope === 'quarter'">
                            @foreach (range(1, 4) as $quarter)
                                <option value="{{ $quarter }}" x-show="scope === 'quarter'"
                                    @selected($scope === 'quarter' && $scopeValue === $quarter)>Triwulan {{ $quarter }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Semester" x-show="scope === 'semester'">
                            @foreach (range(1, 2) as $semester)
                                <option value="{{ $semester }}" x-show="scope === 'semester'"
                                    @selected($scope === 'semester' && $scopeValue === $semester)>Semester {{ $semester }}</option>
                            @endforeach
                        </optgroup>
                    </x-ui.select>
                </div>
                <div>
                    <label class="ui-filter-label" for="rkas-search-filter">Pencarian</label>
                    <x-ui.input id="rkas-search-filter" name="q" :value="$search"
                        placeholder="Kode rekening atau uraian" />
                </div>
                <details class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3 lg:col-span-4">
                    <summary class="cursor-pointer text-sm font-bold text-[var(--ui-fg-strong)]">Filter lanjutan (Program / Subprogram / Kegiatan)</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="ui-filter-label" for="rkas-program-filter">Program</label>
                    <x-ui.select id="rkas-program-filter" name="program" x-on:change="$el.form.submit()">
                        <option value="">Semua program</option>
                        @foreach ($programOptions as $option)
                            <option value="{{ $option['program'] }}" @selected($programFilter === $option['program'])>
                                {{ $option['program'] }} - {{ $option['program_name'] }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <label class="ui-filter-label" for="rkas-subprogram-filter">Subprogram</label>
                    <x-ui.select id="rkas-subprogram-filter" name="subprogram" x-on:change="$el.form.submit()">
                        <option value="">Semua subprogram</option>
                        @foreach ($subprogramOptions as $option)
                            <option value="{{ $option['subprogram'] }}" @selected($subprogramFilter === $option['subprogram'])>
                                {{ $option['subprogram'] }} - {{ $option['subprogram_name'] }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <label class="ui-filter-label" for="rkas-activity-filter">Kegiatan</label>
                    <x-ui.select id="rkas-activity-filter" name="activity" x-on:change="$el.form.submit()">
                        <option value="">Semua kegiatan</option>
                        @foreach ($activityOptions as $option)
                            <option value="{{ $option['activity'] }}" @selected($activityFilter === $option['activity'])>
                                {{ $option['activity'] }} - {{ $option['activity_name'] }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                    </div>
                </details>
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="flex items-end">
                    <x-ui.button type="submit" icon="filter">Tampilkan</x-ui.button>
                </div>
            </form>
        </section>

        @if (in_array($scope, ['quarter', 'semester'], true) && $scopeValue > 0)
            @php($monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'])
            <x-section-card title="Rincian pembagian pagu seperti PDF RKAS" :description="'Perbandingan rincian ' . $periodLabel . ' berdasarkan periode RKAS yang tersaring.'" :padding="false">
                @php($periodColumns = $scope === 'quarter' ? $periodMonths : [1, 2])
                <div
                    class="mx-4 mt-4 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3 text-sm text-[var(--ui-fg-strong)] sm:mx-6">
                    <p class="font-semibold">Format mengikuti PDF RKAS</p>
                    <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">Setiap baris menunjukkan satu item anggaran. Nilai
                        periode berasal dari pembagian pagu ARKAS pada field TW, sehingga jumlah seluruh periode harus
                        sama dengan pagu tahunan.</p>
                </div>
                <div class="mx-4 mt-4 overflow-x-auto rounded-xl border sm:mx-6" style="border-color: var(--ui-line)">
                    <table class="min-w-[980px] w-full divide-y text-sm" style="border-color: var(--ui-line)">
                        <thead style="background: var(--ui-surface-soft)">
                            <tr>
                                <th class="w-12 px-3 py-2 text-center text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">No</th>
                                <th class="min-w-[300px] px-3 py-2 text-left text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">Uraian / Kode Rekening</th>
                                @if ($scope === 'quarter')
                                    @foreach ($periodMonths as $month)
                                        <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                            style="color: var(--ui-fg-muted)">{{ $monthNames[$month] }}</th>
                                    @endforeach
                                @else
                                    @foreach ($periodColumns as $semester)
                                        <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                            style="color: var(--ui-fg-muted)">Tahap {{ $semester }}</th>
                                    @endforeach
                                @endif
                                <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--ui-line)">
                            @php($detailNo = 0)
                            @php($displayRows = $scope === 'quarter' ? $periodDetailRows : $periodPlanningRows)
                            @forelse($displayRows->groupBy('activity_code') as $activityCode => $detailRows)
                                <tr style="background: var(--ui-surface-soft)">
                                    <td></td>
                                    <td colspan="{{ count($periodColumns) + 2 }}"
                                        class="px-3 py-2 text-xs font-bold" style="color: var(--ui-fg-strong)">
                                        <span class="font-mono">{{ $activityCode ?: 'Tanpa kode kegiatan' }}</span>
                                        <span class="ml-2"
                                            style="color: var(--ui-fg-muted)">{{ $detailRows->first()['activity_name'] }}</span>
                                    </td>
                                </tr>
                                @foreach ($detailRows as $detail)
                                    @php($detailNo++)
                                    <tr>
                                        <td class="px-3 py-2 text-center text-xs" style="color: var(--ui-fg-muted)">
                                            {{ $detailNo }}</td>
                                        <td class="px-3 py-2">
                                            <p class="font-semibold" style="color: var(--ui-fg-strong)">
                                                {{ $detail['description'] }}</p>
                                            <p class="mt-1 text-xs" style="color: var(--ui-fg-muted)"><span
                                                    class="font-mono">{{ $detail['account_code'] }}</span> ·
                                                {{ rtrim(rtrim(number_format($detail['volume'], 2, ',', '.'), '0'), ',') }}
                                                {{ $detail['unit'] }} · {{ $rupiah($detail['unit_price']) }}</p>
                                        </td>
                                        @foreach ($periodColumns as $month)
                                            <td class="whitespace-nowrap px-3 py-2 text-right text-xs">
                                                {{ $detail['months'][$month] > 0 ? $rupiah($detail['months'][$month]) : '—' }}
                                            </td>
                                        @endforeach
                                        <td class="whitespace-nowrap px-3 py-2 text-right text-xs font-bold"
                                            style="color: var(--theme-content-accent)">{{ $rupiah($detail['total']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ count($periodColumns) + 3 }}"
                                        class="px-5 py-10 text-center text-sm" style="color: var(--ui-fg-muted)">Belum
                                        ada rincian bulanan yang terpetakan untuk filter ini. Total triwulan tetap
                                        dihitung dari pembagian pagu ARKAS (TW).</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-section-card>
        @endif

        @if (!in_array($scope, ['quarter', 'semester'], true))
            <x-section-card title="Rincian Penganggaran RKAS"
                description="Pagu anggaran dan realisasi BKU pada konteks aktif." :padding="false">
                <x-slot:actions>
                    <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">•
                        {{ number_format($items->total(), 0, ',', '.') }} data</span>
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
                    <table class="min-w-full divide-y text-sm" style="border-color: var(--ui-line)">
                        <thead style="background: var(--ui-surface-soft)">
                            <tr>
                                <th class="w-12 px-3 py-2 text-center text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">No</th>
                                <th class="px-3 py-2 text-left text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">Uraian / Kode Rekening</th>
                                <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">Volume</th>
                                <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">Pagu Periode</th>
                                <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">Realisasi Dibukukan</th>
                                <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                                    style="color: var(--ui-fg-muted)">Selisih</th>
                            </tr>
                        </thead>
                        <tbody x-data="{ openActivities: {}, openAccounts: {} }" class="divide-y" style="border-color: var(--ui-line)">
                            @forelse($rkasGroups as $activityIndex => $activity)
                                @php($activityKey = 'activity-' . $activityIndex)
                                <tr x-init="openActivities['{{ $activityKey }}'] = true" style="background: var(--ui-surface-soft)">
                                    <td class="px-3 py-1.5 text-center text-xs font-semibold"
                                        style="color: var(--ui-fg-muted)">{{ $activityIndex + 1 }}</td>
                                    <td class="px-3 py-1.5 text-xs">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 text-left font-bold"
                                            style="color: var(--ui-fg-strong)"
                                            x-on:click="openActivities['{{ $activityKey }}'] = ! openActivities['{{ $activityKey }}']">
                                            <span
                                                class="inline-flex h-5 w-5 items-center justify-center rounded-full border"
                                                style="color: var(--theme-content-accent); border-color: color-mix(in srgb, var(--theme-content-accent) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 55%, var(--ui-surface-base))">
                                                <x-ui.icon name="chevron-down" size="xs"
                                                    x-show="openActivities['{{ $activityKey }}']" />
                                                <x-ui.icon name="chevron-right" size="xs"
                                                    x-show="! openActivities['{{ $activityKey }}']" />
                                            </span>
                                            <span class="flex flex-wrap items-center gap-1.5">
                                                <span class="rounded border px-1.5 py-0.5 font-mono text-[10px]"
                                                    title="{{ $activity['program_name'] ?: 'Program' }}"
                                                    style="color: var(--ui-fg-muted); border-color: var(--ui-line-strong)">{{ $activity['program_code'] }}</span>
                                                <span style="color: var(--ui-fg-muted)">›</span>
                                                <span class="rounded border px-1.5 py-0.5 font-mono text-[10px]"
                                                    title="{{ $activity['subprogram_name'] ?: 'Subprogram' }}"
                                                    style="color: var(--ui-fg-muted); border-color: var(--ui-line-strong)">{{ $activity['subprogram_code'] }}</span>
                                                <span style="color: var(--ui-fg-muted)">›</span>
                                                <span class="font-mono">{{ $activity['code'] }}</span>
                                                <span>· {{ $activity['name'] }}</span>
                                            </span>
                                        </button>
                                    </td>
                                    <td></td>
                                    <td class="px-3 py-1.5 text-right text-xs font-bold"
                                        style="color: var(--theme-content-accent)">{{ $rupiah($activity['amount']) }}
                                    </td>
                                    <td class="px-3 py-1.5 text-right text-xs font-semibold text-emerald-700">
                                        {{ $rupiah($activity['realization']) }}</td>
                                    <td class="px-3 py-1.5 text-right text-xs font-bold"
                                        style="color: var(--ui-fg-muted)">{{ $rupiah($activity['remaining']) }}</td>
                                </tr>
                                @foreach ($activity['accounts'] as $accountIndex => $account)
                                    @php($accountKey = $activityKey . '-account-' . $accountIndex)
                                    <tr x-show="openActivities['{{ $activityKey }}']" x-init="openAccounts['{{ $accountKey }}'] = true"
                                        style="background: var(--ui-surface-muted)">
                                        <td></td>
                                        <td class="px-3 py-1 pl-10 text-xs">
                                            <button type="button"
                                                class="inline-flex items-center gap-2 text-left font-semibold"
                                                style="color: var(--ui-fg-strong)"
                                                x-on:click="openAccounts['{{ $accountKey }}'] = ! openAccounts['{{ $accountKey }}']">
                                                <span
                                                    class="inline-flex h-4 w-4 items-center justify-center rounded-full border"
                                                    style="color: var(--theme-accent-strong); border-color: color-mix(in srgb, var(--theme-accent-strong) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 35%, var(--ui-surface-base))">
                                                    <x-ui.icon name="chevron-down" size="xs"
                                                        x-show="openAccounts['{{ $accountKey }}']" />
                                                    <x-ui.icon name="chevron-right" size="xs"
                                                        x-show="! openAccounts['{{ $accountKey }}']" />
                                                </span>
                                                <span class="font-mono">{{ $account['code'] }}</span>
                                            </button>
                                        </td>
                                        <td></td>
                                        <td class="px-3 py-1 text-right text-xs font-semibold"
                                            style="color: var(--theme-content-accent)">
                                            {{ $rupiah($account['amount']) }}</td>
                                        <td class="px-3 py-1 text-right text-xs text-emerald-700">
                                            {{ $rupiah($account['realization']) }}</td>
                                        <td class="px-3 py-1 text-right text-xs" style="color: var(--ui-fg-muted)">
                                            {{ $rupiah($account['remaining']) }}</td>
                                    </tr>
                                    @foreach ($account['items'] as $item)
                                        <tr x-show="openActivities['{{ $activityKey }}'] && openAccounts['{{ $accountKey }}']"
                                            class="text-xs">
                                            <td></td>
                                            <td class="px-3 py-1 pl-20">
                                                <p class="truncate font-semibold leading-tight"
                                                    style="color: var(--ui-fg-strong)"
                                                    title="{{ $item->description ?: 'Tanpa uraian' }}">
                                                    {{ $item->description ?: 'Tanpa uraian' }}</p>
                                            </td>
                                            <td class="px-3 py-1 text-right">
                                                {{ rtrim(rtrim(number_format($item->volume, 2, ',', '.'), '0'), ',') }}
                                                {{ $item->unit }}</td>
                                            <td class="px-3 py-1 text-right font-semibold"
                                                style="color: var(--theme-content-accent)">
                                                {{ $rupiah($item->display_amount) }}</td>
                                            <td class="px-3 py-1 text-right text-emerald-700">
                                                {{ $rupiah($item->realization) }}</td>
                                            <td class="px-3 py-1 text-right" style="color: var(--ui-fg-muted)">
                                                {{ $rupiah($item->variance) }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-14 text-center">
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


                <x-ui.server-pagination :paginator="$items" noun="data" compact />
            </x-section-card>
        @endif
    </div>
</x-layouts.tailwind-app>
