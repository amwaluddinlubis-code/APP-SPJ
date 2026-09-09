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
                    <button class="inline-flex w-fit items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/20 transition hover:bg-white/20">
                        <x-ui-icon name="sync" class="h-4 w-4" />
                        <span>Sinkron Semua ARKAS</span>
                    </button>
                </form>
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item
                    label="Total Anggaran"
                    :value="$rupiah($budget)"
                    hint="RKAS tersinkron"
                    value-class="text-indigo-700"
                    icon="budget"
                    icon-class="text-indigo-600"
                />
                <x-stat-item
                    label="Realisasi BKU"
                    :value="$rupiah($spent)"
                    hint="Belanja tercatat"
                    value-class="text-emerald-700"
                    icon="transaction"
                    icon-class="text-emerald-600"
                />
                <x-stat-item
                    label="Sisa Anggaran"
                    :value="$rupiah(abs($remaining))"
                    :hint="$remaining < 0 ? 'Melewati anggaran' : 'Belum direalisasikan'"
                    :value-class="$remaining < 0 ? 'text-rose-600' : 'text-slate-800'"
                    icon="balance"
                    :icon-class="$remaining < 0 ? 'text-rose-600' : 'text-amber-600'"
                />
                <x-stat-item
                    label="Kegiatan RKAS"
                    :value="number_format($activityCount, 0, ',', '.')"
                    hint="Kegiatan tersinkron"
                    value-class="text-slate-800"
                    icon="work"
                    icon-class="text-sky-600"
                />
            </div>
        </x-page-header>

        <section class="flex flex-col gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 sm:flex-row sm:items-center sm:px-6">
            <span class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-xs font-bold text-white">
                <x-ui-icon name="budget" class="h-4 w-4" />
                <span>SISA TERSEDIA</span>
            </span>
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

        <section class="ui-filter-panel" x-data="{ scope: @js($scope) }">
            <form method="GET" class="ui-filter-grid">
                <div>
                    <label class="ui-filter-label" for="rkas-scope">Tampilan periode</label>
                    <x-ui.select id="rkas-scope" name="scope" x-model="scope">
                        <option value="year">Tahun anggaran</option>
                        <option value="month">Bulan</option>
                        <option value="quarter">Triwulan</option>
                        <option value="semester">Semester</option>
                    </x-ui.select>
                </div>
                <div x-show="scope !== 'year'" x-cloak>
                    <label class="ui-filter-label" for="rkas-scope-value">Periode</label>
                    <x-ui.select id="rkas-scope-value" name="scope_value">
                        <option value="">Pilih periode</option>
                        <optgroup label="Bulan" x-show="scope === 'month'">
                            @foreach(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $month)
                                <option value="{{ $loop->iteration }}" x-show="scope === 'month'" @selected($scope === 'month' && $scopeValue === $loop->iteration)>{{ $month }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Triwulan" x-show="scope === 'quarter'">
                            @foreach(range(1, 4) as $quarter)
                                <option value="{{ $quarter }}" x-show="scope === 'quarter'" @selected($scope === 'quarter' && $scopeValue === $quarter)>Triwulan {{ $quarter }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Semester" x-show="scope === 'semester'">
                            @foreach(range(1, 2) as $semester)
                                <option value="{{ $semester }}" x-show="scope === 'semester'" @selected($scope === 'semester' && $scopeValue === $semester)>Semester {{ $semester }}</option>
                            @endforeach
                        </optgroup>
                    </x-ui.select>
                </div>
                <div>
                    <label class="ui-filter-label" for="rkas-search-filter">Pencarian</label>
                    <x-ui.input id="rkas-search-filter" name="q" :value="$search" placeholder="Kode rekening atau uraian" />
                </div>
                <div>
                    <label class="ui-filter-label" for="rkas-program-filter">Program</label>
                    <x-ui.select id="rkas-program-filter" name="program" x-on:change="$el.form.submit()">
                        <option value="">Semua program</option>
                        @foreach($programOptions as $option)
                            <option value="{{ $option['program'] }}" @selected($programFilter === $option['program'])>{{ $option['program'] }}{{ $option['program_name'] ? ' · '.$option['program_name'] : '' }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <label class="ui-filter-label" for="rkas-subprogram-filter">Subprogram</label>
                    <x-ui.select id="rkas-subprogram-filter" name="subprogram" x-on:change="$el.form.submit()">
                        <option value="">Semua subprogram</option>
                        @foreach($subprogramOptions as $option)
                            <option value="{{ $option['subprogram'] }}" @selected($subprogramFilter === $option['subprogram'])>{{ $option['subprogram'] }}{{ $option['subprogram_name'] ? ' · '.$option['subprogram_name'] : '' }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <label class="ui-filter-label" for="rkas-activity-filter">Kegiatan</label>
                    <x-ui.select id="rkas-activity-filter" name="activity">
                        <option value="">Semua kegiatan</option>
                        @foreach($activityOptions as $option)
                            <option value="{{ $option['activity'] }}" @selected($activityFilter === $option['activity'])>{{ $option['activity'] }} · {{ $option['activity_name'] }}</option>
                        @endforeach
                    </x-ui.select>
                    <p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">Subprogram mengikuti Program, dan Kegiatan mengikuti Subprogram.</p>
                </div>
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="flex items-end">
                    <x-ui.button type="submit" icon="filter">Tampilkan</x-ui.button>
                </div>
            </form>
        </section>

        <x-section-card
            title="Rincian Penganggaran RKAS"
            description="Pagu anggaran dan realisasi BKU pada konteks aktif."
            :padding="false"
        >
            <x-slot:actions>
                <form method="GET">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <input type="hidden" name="scope" value="{{ $scope }}">
                    <input type="hidden" name="scope_value" value="{{ $scopeValue }}">
                    <input type="hidden" name="program" value="{{ $programFilter }}">
                    <input type="hidden" name="subprogram" value="{{ $subprogramFilter }}">
                    <input type="hidden" name="activity" value="{{ $activityFilter }}">
                    <x-ui.search-group
                        id="rkas-search"
                        name="q"
                        :value="$search"
                        placeholder="Cari rekening atau kegiatan"
                        width="w-80"
                    />
                </form>

                <form method="GET" class="ui-toolbar-group flex items-center gap-2 text-[13px]">
                    @if($search !== '')
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif
                    <input type="hidden" name="scope" value="{{ $scope }}">
                    <input type="hidden" name="scope_value" value="{{ $scopeValue }}">
                    <input type="hidden" name="program" value="{{ $programFilter }}">
                    <input type="hidden" name="subprogram" value="{{ $subprogramFilter }}">
                    <input type="hidden" name="activity" value="{{ $activityFilter }}">
                    <label for="rkas-per-page" class="font-semibold" style="color: var(--ui-fg-muted)">Baris</label>
                    <select
                        id="rkas-per-page"
                        name="per_page"
                        class="ui-select !min-h-9 !w-auto !py-1.5 !text-[13px]"
                    >
                        @foreach([15, 30, 50, 100] as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} baris</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-3 py-1.5 font-semibold transition hover:brightness-95" style="border-color: var(--ui-line); color: var(--ui-fg-muted); background: var(--ui-bg)">
                        <x-ui-icon name="queue" class="h-4 w-4" />
                        <span>Terapkan</span>
                    </button>
                    <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">• {{ number_format($items->total(), 0, ',', '.') }} data</span>
                </form>

                <a class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition hover:brightness-95" style="border-color: var(--ui-line); color: var(--ui-fg-muted); background: var(--ui-bg)" href="{{ route('synced-data.show', 'rkas') }}">
                    <x-ui-icon name="database" class="h-4 w-4" />
                    <span>Data Mentah</span>
                </a>
            </x-slot:actions>

            <div class="mx-4 mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 rounded-lg border px-3 py-2 text-xs" style="border-color: var(--ui-line); background: var(--ui-surface-soft); color: var(--ui-fg-muted)">
                <span class="font-bold uppercase tracking-wide" style="color: var(--ui-fg-strong)">Subtotal tersaring</span>
                <span>Anggaran <strong style="color: var(--theme-content-accent)">{{ $rupiah($items->getCollection()->sum('display_amount')) }}</strong></span>
                <span>Realisasi <strong class="text-emerald-700">{{ $rupiah($items->getCollection()->sum('realization')) }}</strong></span>
                <span>Selisih <strong style="color: var(--ui-fg-strong)">{{ $rupiah($items->getCollection()->sum('variance')) }}</strong></span>
            </div>

            <div class="mt-2 overflow-x-auto rounded-xl border" style="border-color: var(--ui-line)">
                <table class="min-w-full divide-y text-sm" style="border-color: var(--ui-line)">
                    <thead style="background: var(--ui-surface-soft)">
                        <tr>
                            <th class="w-12 px-3 py-2 text-center text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">No</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">Hierarki RKAS</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">Volume</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">Anggaran Periode</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">Realisasi</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">Selisih</th>
                        </tr>
                    </thead>
                    <tbody x-data="{ openActivities: {}, openAccounts: {} }" class="divide-y" style="border-color: var(--ui-line)">
                        @forelse($rkasGroups as $activityIndex => $activity)
                            @php($activityKey = 'activity-'.$activityIndex)
                            <tr x-init="openActivities['{{ $activityKey }}'] = true" style="background: var(--ui-surface-soft)">
                                <td class="px-3 py-1.5 text-center text-xs font-semibold" style="color: var(--ui-fg-muted)">{{ $activityIndex + 1 }}</td>
                                <td class="px-3 py-1.5 text-xs">
                                    <button type="button" class="inline-flex items-center gap-2 text-left font-bold" style="color: var(--ui-fg-strong)" x-on:click="openActivities['{{ $activityKey }}'] = ! openActivities['{{ $activityKey }}']">
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border" style="color: var(--theme-content-accent); border-color: color-mix(in srgb, var(--theme-content-accent) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 55%, var(--ui-surface-base))">
                                            <x-ui.icon name="chevron-down" size="xs" x-show="openActivities['{{ $activityKey }}']" />
                                            <x-ui.icon name="chevron-right" size="xs" x-show="! openActivities['{{ $activityKey }}']" />
                                        </span>
                                        <span class="flex flex-wrap items-center gap-1.5">
                                            <span class="rounded border px-1.5 py-0.5 font-mono text-[10px]" title="{{ $activity['program_name'] ?: 'Program' }}" style="color: var(--ui-fg-muted); border-color: var(--ui-line-strong)">{{ $activity['program_code'] }}</span>
                                            <span style="color: var(--ui-fg-muted)">›</span>
                                            <span class="rounded border px-1.5 py-0.5 font-mono text-[10px]" title="{{ $activity['subprogram_name'] ?: 'Subprogram' }}" style="color: var(--ui-fg-muted); border-color: var(--ui-line-strong)">{{ $activity['subprogram_code'] }}</span>
                                            <span style="color: var(--ui-fg-muted)">›</span>
                                            <span class="font-mono">{{ $activity['code'] }}</span>
                                            <span>· {{ $activity['name'] }}</span>
                                        </span>
                                    </button>
                                </td>
                                <td></td>
                                <td class="px-3 py-1.5 text-right text-xs font-bold" style="color: var(--theme-content-accent)">{{ $rupiah($activity['amount']) }}</td>
                                <td class="px-3 py-1.5 text-right text-xs font-semibold text-emerald-700">{{ $rupiah($activity['realization']) }}</td>
                                <td class="px-3 py-1.5 text-right text-xs font-bold" style="color: var(--ui-fg-muted)">{{ $rupiah($activity['amount'] - $activity['realization']) }}</td>
                            </tr>
                            @foreach($activity['accounts'] as $accountIndex => $account)
                                @php($accountKey = $activityKey.'-account-'.$accountIndex)
                                <tr x-show="openActivities['{{ $activityKey }}']" x-init="openAccounts['{{ $accountKey }}'] = true" style="background: var(--ui-surface-muted)">
                                    <td></td>
                                    <td class="px-3 py-1 pl-10 text-xs">
                                        <button type="button" class="inline-flex items-center gap-2 text-left font-semibold" style="color: var(--ui-fg-strong)" x-on:click="openAccounts['{{ $accountKey }}'] = ! openAccounts['{{ $accountKey }}']">
                                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border" style="color: var(--theme-accent-strong); border-color: color-mix(in srgb, var(--theme-accent-strong) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 35%, var(--ui-surface-base))">
                                                <x-ui.icon name="chevron-down" size="xs" x-show="openAccounts['{{ $accountKey }}']" />
                                                <x-ui.icon name="chevron-right" size="xs" x-show="! openAccounts['{{ $accountKey }}']" />
                                            </span>
                                            <span class="font-mono">{{ $account['code'] }}</span>
                                        </button>
                                    </td>
                                    <td></td>
                                    <td class="px-3 py-1 text-right text-xs font-semibold" style="color: var(--theme-content-accent)">{{ $rupiah($account['amount']) }}</td>
                                    <td class="px-3 py-1 text-right text-xs text-emerald-700">{{ $rupiah($account['realization']) }}</td>
                                    <td class="px-3 py-1 text-right text-xs" style="color: var(--ui-fg-muted)">{{ $rupiah($account['amount'] - $account['realization']) }}</td>
                                </tr>
                                @foreach($account['items'] as $item)
                                    <tr x-show="openActivities['{{ $activityKey }}'] && openAccounts['{{ $accountKey }}']" class="text-xs">
                                        <td></td>
                                        <td class="px-3 py-1 pl-20">
                                            <p class="truncate font-semibold leading-tight" style="color: var(--ui-fg-strong)" title="{{ $item->description ?: 'Tanpa uraian' }}">{{ $item->description ?: 'Tanpa uraian' }}</p>
                                        </td>
                                        <td class="px-3 py-1 text-right">{{ rtrim(rtrim(number_format($item->volume, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                                        <td class="px-3 py-1 text-right font-semibold" style="color: var(--theme-content-accent)">{{ $rupiah($item->display_amount) }}</td>
                                        <td class="px-3 py-1 text-right text-emerald-700">{{ $rupiah($item->realization) }}</td>
                                        <td class="px-3 py-1 text-right" style="color: var(--ui-fg-muted)">{{ $rupiah($item->variance) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-14 text-center">
                                    <p class="text-sm font-semibold" style="color: var(--ui-fg-strong)">Belum ada RKAS.</p>
                                    <p class="mt-1 text-base" style="color: var(--ui-fg-muted)">Jalankan sinkronisasi atau ubah kata kunci pencarian.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hidden overflow-x-auto" data-pagination="none">
                <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]">
                        <tr>
                            <th class="px-5 py-3 text-center text-[13px] font-bold uppercase tracking-wide text-slate-500">No</th>
                            <th class="px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Tanggal RKAS</th>
                            <th class="px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Kode Rekening</th>
                            <th class="min-w-[260px] px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Uraian / Barang</th>
                            <th class="min-w-[220px] px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Kegiatan</th>
                            <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Volume</th>
                            <th class="px-4 py-3 text-left text-[13px] font-bold uppercase tracking-wide text-slate-500">Satuan</th>
                            <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Harga Satuan</th>
                            <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Anggaran Periode</th>
                            <th class="px-4 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Realisasi</th>
                            <th class="px-5 py-3 text-right text-[13px] font-bold uppercase tracking-wide text-slate-500">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)] text-sm">
                        @forelse($items as $index => $item)
                            <tr class="transition hover:bg-indigo-50/50">
                                <td class="px-5 py-4 text-center text-[13px] font-semibold text-slate-400">{{ $items->firstItem() + $index }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-[13px] font-semibold text-slate-700">{{ $item->source_created_at ? \Illuminate\Support\Carbon::parse($item->source_created_at)->translatedFormat('d M Y') : '—' }}</td>
                                <td class="px-4 py-4"><span class="font-mono text-[13px] font-bold text-indigo-700">{{ $item->account_code ?: '—' }}</span></td>
                                <td class="px-4 py-4"><p class="line-clamp-2 text-sm font-semibold text-slate-800">{{ $item->description ?: 'Tanpa uraian' }}</p><p class="mt-1 font-mono text-[13px] text-slate-400">{{ $item->source_rapbs_id }}</p></td>
                                <td class="px-4 py-4"><p class="font-mono text-[13px] font-semibold text-sky-700">{{ $item->activity_code ?: '—' }}</p><p class="mt-1 line-clamp-2 text-[13px] text-slate-500">{{ $item->activity_name ?: 'Kegiatan belum diisi' }}</p></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right text-sm">{{ rtrim(rtrim(number_format($item->volume, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-4 py-4 text-sm text-slate-500">{{ $item->unit }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right text-sm">{{ $rupiah($item->unit_price) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold text-indigo-700">{{ $rupiah($item->display_amount) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-medium text-emerald-700">{{ $rupiah($item->realization) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold {{ $item->variance < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ $item->variance < 0 ? '- ' : '' }}{{ $rupiah(abs($item->variance)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-5 py-14 text-center">
                                    <p class="text-sm font-semibold text-slate-700">Belum ada RKAS.</p>
                                    <p class="mt-1 text-base text-slate-500">Jalankan sinkronisasi atau ubah kata kunci pencarian.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-ui.server-pagination :paginator="$items" noun="data" compact />
        </x-section-card>
    </div>
</x-layouts.tailwind-app>
